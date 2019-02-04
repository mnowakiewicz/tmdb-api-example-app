<?php

namespace AppBundle\Controller;

use GuzzleHttp\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    private const PATH_GENRE_LIST = 'genre/movie/list';
    private const PATH_DISCOVER_MOVIE = 'discover/movie';
    private const POSTER_BASE_URL = 'http://image.tmdb.org/t/p/w185/';

    private const FORMAT_PATH_RECOMMENDATIONS = 'movie/%d/recommendations';

    /**
     * @Route("/", name="homepage")
     * @throws \Exception
     */
    public function indexAction(): Response
    {
        if ($this->get('session')->get('movies')) {
            /** @var array $movies */
            $movies = $this->get('session')->get('movies');
        } else {
            /** @var array $movies */
            $movies = $this->getMovies();
            $this->get('session')->set('movies', $movies);
        }
        return $this->render('default/index.html.twig', [
            'movies' => $movies,
            'poster_base_url' => self::POSTER_BASE_URL,
        ]);
    }

    /**
     * @Route(path="/show/", name="show_recommended_movie")
     * @Method({"POST"})
     * @param Request $request
     * @return Response
     */
    public function showRecommendedMovieAction(Request $request): Response
    {
        $content = $request->getContent();
        $ids = $this->getIdsFromContent($content);
        $recommendations = $this->getRecommendedMoviesByIds($ids);
        $recommendedMovies = $this->get8RandomMoviesFromRecommendations($recommendations);

        return $this->render('default/recommended_movies.html.twig', [
            'movies' => $recommendedMovies,
            'poster_base_url' => self::POSTER_BASE_URL
        ]);
    }

    /**
     * @return array
     */
    public function getMovies(): array
    {
        $genres = $this->getContentsFromTheMovieDB(self::PATH_GENRE_LIST)['genres'];
        $movies = [];
        foreach ($genres as $genre) {
            $results = $this->getMoviesByGenreId($genre['id']);
            if (!empty($results)) {
                $size = count($movies);
                $i = 0;
                while (count($movies) < $size + 3 && $i < 6) {
                    $rand = array_rand($results);
                    if (!array_key_exists($results[$rand]['id'], $movies)){
                        $movies[$results[$rand]['id']] = $results[$rand];
                    }
                    $i++;
                }
            }

        }
        return $movies;
    }

    public function getMoviesByGenreId(string $genreId) {
        return $this->getContentsFromTheMovieDB(self::PATH_DISCOVER_MOVIE, [
            'sort_by' => 'vote_average.desc',
            'vote_count.gte' => 3000,
            'with_genres' => $genreId,
            'release_date.lte' => (new \DateTime('now'))->modify('-2 years')->format('Y-m-d'),
            'vote_average.gte' => 7.0,
            'vote_average.lte' => 10.0
        ])['results'];
    }

    /**
     * @param string $path
     * @param array $parameters
     * @return array
     */
    public function getContentsFromTheMovieDB(string $path, array $parameters = []): array
    {
        $client = new Client([
            'base_uri' => $this->getParameter('themoviedb_api_host')
        ]);

        $APIVersion = $this->getParameter('themoviedb_api_version');

        $uri = '/' . $APIVersion . '/' . $path . $this->parametersToString($parameters);
        $response = $client->get($uri);
        $body = $response->getBody();
        $contents =  json_decode($body->getContents(), true);
        return $contents;
    }

    /**
     * @param array $parameters
     * @return string
     */
    private function parametersToString(array $parameters): string
    {
        $string = '?';

        foreach ($parameters as $key => $parameter) {
            $string .= $key . '=' . $parameter . '&';
        }
        $string .= 'api_key=' . $this->getParameter('themoviedb_api_key');
        return $string;
    }

    /**
     * @param string $content
     * @return array
     */
    private function getIdsFromContent(string $content): array
    {
        $ids = [];
        $pieces = explode('&', $content);

        foreach ($pieces as $piece) {
            $ids[] = (int) substr($piece, 6);
        }

        return $ids;
    }

    /**
     * @param array $ids
     * @return array
     */
    private function getRecommendedMoviesByIds(array $ids): array
    {
        $recommendations = [];
        foreach ($ids as $id) {
            $results = $this->getContentsFromTheMovieDB(sprintf(self::FORMAT_PATH_RECOMMENDATIONS, $id))["results"];
            $recommendations = array_merge($recommendations, $results);
        }

        return $recommendations;
    }

    /**
     * @param array $recommendations
     * @return array
     */
    private function get8RandomMoviesFromRecommendations(array $recommendations): array
    {
        $randomKeys = array_rand($recommendations, 8);
        $recommendedMovies = [];

        foreach ($randomKeys as $key) {
            $recommendedMovies[] = $recommendations[$key];
        }

        return $recommendedMovies;
    }
}
