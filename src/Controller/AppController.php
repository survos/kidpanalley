<?php

namespace App\Controller;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\Song;
use App\Entity\Video;
use App\Repository\SongRepository;
use App\Repository\VideoRepository;
use App\Services\AppService;
use App\Services\DocxConversion;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class AppController extends AbstractController
{
    private $auth;

    final const ENDPOINT = 'https://www.kidpanalley.org/wp-json/wp/v2/pages';

    public function __construct(
        private AppService $appService,
                                private readonly Environment $twig,
                                private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.environment%')] private string $env,
    )
    {
    }

    // browse with meili or doctrine
    #[Route(path: '/meili/{shortClass}', name: 'app_browse', methods: ['GET'])]
    #[Route(path: '/doctrine/{shortClass}', name: 'app_browse_with_doctrine', methods: ['GET'])]
    public function browse(Request $request, IriConverterInterface $iriConverter, string $shortClass) : Response
    {

        // get the columns based on the type
        $columns = [];
        $columns = [
            ['name' => 'title', 'block' => $shortClass . 'Title', 'order' => 2],
            ['name' => 'year'],
            'school',
            'id'
        ];

        $useMeili = $request->get('_route') == 'app_browse';
        $class = match ($shortClass) {
            'Song' => Song::class,
            'Video' => Video::class
        };
        $apiCall = $useMeili
            ? '/api/meili/' . $shortClass
            : $iriConverter->getIriFromResource($class, operation: new GetCollection(),
                context: $context??[])
            ;

        return $this->render('app/meili.html.twig', [
            'apiCall' => $apiCall,
            'useMeili' => $useMeili,
            'columns' => $columns,
            'class' => $class
        ]);
    }

    private function getAuth()
    {
        $u = trim($this->getParameter('api_username'));
        $p = trim($this->getParameter('api_password'));
        return [$u, $p];
    }

    #[Route(path: '/', name: 'app_homepage', methods: ['GET'])]
    #[Route(path: '/admin', name: 'admin', methods: ['GET'])]
    public function homepage(SongRepository $songRepository, VideoRepository $videoRepository,
    #[Autowire('%kpa.version%')] string $applicationVersion
    )
    {
        $user = $this->getUser();
        return $this->render('app/homepage.html.twig', [
            'user' => $user,
            'featured' => $songRepository->findBy([], ['id' => 'DESC'], 1),
            'featuredVideo' => $videoRepository->findBy([], ['id' => 'DESC'], 1),
            'songCount' => $songRepository->count([]),
            'videoCount' => $videoRepository->count([])
        ]);
    }

    private function loadLyrics($songs)
    {
        $this->appService->loadLyricsViaDropbox('/');
        // @todo: fetch lyrics from Dropbox
    }

    private function loadBestFriendsLyrics($songs)
    {


        $file = '../bf-lyrics.docx';
        if (!file_exists($file)) {
            throw new \Exception("File $file does not exist");
        }
        $converter = new DocxConversion($file);
        $text = $converter->convertToText();

        /** @var Song $currentSong */
        $currentSong = null;
        $songLyrics = '';
        foreach (explode("\n", (string) $text) as $s) {
            $s = trim($s);

            // total hack, but too lazy to do it right
            /** @var Song $song */
            foreach ($songs as $song) {
                if ($s == $song->getTitle()) {
                    // found a song!
                    if ($songLyrics && $currentSong) {
                        $currentSong->setLyrics($songLyrics);
                        $songLyrics = '';
                    }
                    $currentSong = $song;
                } else {
                    //
                }
            }
            if ($currentSong) {
                $songLyrics .= $s;
            }
        }
        return $text;


    }

    private function createPage(Song $song)
    {
        $content = $this->twig->render("song.html.twig", [
            'song' => $song,
        ]);
    }

    private function getSongs()
    {
        return $this->em->getRepository(Song::class)->findAll();
    }

    #[Route(path: '/song_credits', name: 'app_credits_page')]
    public function credits()
    {
        return $this->render('app/song_credits.html.twig', [
            'songs' => $this->getSongs()
        ]);
    }


    /**
     * Publish a song on the KPA wordpress site
     *
     */
    #[Route(path: '/publish', name: 'app_publish')]
    #[Template('app/publish.html.twig')]
    public function publish(array $options=[])
    {
        [$u, $p] = $this->getAuth();
        $command = sprintf('curl -H "Accept: application/json" -H "Content-Type: application/json" -X POST -d \'{"title":"Test Page","content":"lyrics go here.","type":"page"}\' %s/wp-json.php/posts -u %s:%s',
            'https://www.kidpanalley.org', $u, $p);
//        if ($this->env=='dev') {
//            dd($command);
//        }
        return ['command' => $command];

        $song = null;
        $wordpressPagePayload = (new OptionsResolver())
            ->setDefaults(
                [
                    'type' => 'page',
                    'title' => null,
                    'content' => null
                ]
            )
            ->setRequired(['title', 'content'])
            ->resolve($options);
        /*
        $wordpressPagePayload = [
            'type' => 'page',
            'title' => $song->getTitle(),
            'content' => $content=$this->createPage($song)
        ];
        */
        $client = HttpClient::create();
        $method = 'POST';
        $endPoint = self::ENDPOINT;
        /*
        if ($wordpressId = $song->getWordpressPageId()) {
            // update instead of create
            $endPoint .= '/' . $wordpressId;
            // add id?
            // $method = 'PUT';
        } else {

        }
        */
        $results = $client->request($method, $endPoint, $data = [
            'auth_basic' => $this->getAuth(),
            'json' => $wordpressPagePayload
        ]);
        $command = sprintf('curl -H "Accept: application/json" -H "Content-Type: application/json" -X POST -d \'{"title":"Test Page","content":"lyrics go here.","type":"page"}\' %s/wp-json.php/posts -u %s:%s',
           'https://www.kidpanalley.org', $u, $p);
        dd($command);

        $results = exec($command);
        $response = json_decode($results->getContent(), null, 512, JSON_THROW_ON_ERROR);
        $id = $response->id;
        $song->setWordpressPageId($id);
        dump($id, $endPoint, $data, $results, $response);
        /* lyrics-page, but is this different?
           $client = HttpClient::create();
           $endPoint = self::ENDPOINT . '/1870';
           $results = $client->request('GET', $endPoint, $data = [
               'auth_basic' => $this->getAuth(),
           ]);
           $lyricsPage = json_decode($results->getContent());
           */
    }

    #[Route(path: '/load-kpa-channel', name: 'app_load_youtube_channel')]
    public function loadYoutubeChannel(EntityManagerInterface $em, LoggerInterface $logger, ParameterBagInterface $bag, AppService $appService)
    {
        $key = $bag->get('youtube_api_key');
        $channel = $bag->get('youtube_channel');
        $videos = $appService->fetchYoutubeChannel($key, $channel);
        return $this->redirectToRoute('video_index');
    }

    #[Route(path: '/load-kpa-songs', name: 'app_load_songs')]
    public function loadSongs(AppService $appService)
    {
        $appService->loadSongs();
        return $this->redirectToRoute('song_index');
        return $this->render('app/index.html.twig', [
            'lyrics' => $lyrics,
            'songs' => $songs
        ]);
    }


    #[Route(path: '/load-lyrics-from-files', name: 'app_load_lyrics')]
    public function index(AppService $appService, EntityManagerInterface $em)
    {
        $this->appService->loadLyricsViaDropbox('/');

        $dir = __DIR__ . '/../../data/lyrics';
        $appService->loadLyrics($dir);
        return $this->redirectToRoute('song_index', ['lyrics_only' => true]);
    }

    #[Route(path: '/load-best-friends', name: 'app_load_best_friends')]
    public function bestFriends(EntityManagerInterface $em)
    {
        /** @var Xls $readerXlsx */
        $readerXlsx  = $this->spreadsheet->createReader('Xlsx');
        /** @var Spreadsheet $spreadsheet */
        try {
            $spreadsheet = $readerXlsx->load('/var/www/kpa/best-friends-credits.xlsx');
        } catch (\Exception $exception) {
            dd($exception);
        }
        /** @var Worksheet $sheet */
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($sheet->toArray() as $idx=>$row) {
            if ($idx === 0) {
                $header = $row;
            } else {
                $data = array_combine($header, $row);
                $title = $data['Song Title'];
                if (!$title) {
                    continue;
                }
                // look for the title
//                dump($data);
                if (!$song = $em->getRepository(Song::class)->findOneBy(['title' => $title])) {
                    $song = (new Song())
                        ->setTitle($title);
                    $em->persist($song);
                }
                $song
                    ->setWriters($data['Writers'])
                    ->setMusicians($data['Musicians'])
                    ->setRecordingCredits($data['Recording Credits'])
                    ->setFeaturedArtist($data['Featured Artist']);
                $this->createPage($song);
                $em->flush();
            }
        }
        $songs = $this->getSongs();
        $lyrics = $this->loadBestFriendsLyrics($songs);
        // dd($spreadsheet);
        return $this->render('app/index.html.twig', [
            'controller_name' => 'AppController',
            'lyrics' => $lyrics,
            'songs' => $songs
        ]);
    }
}
