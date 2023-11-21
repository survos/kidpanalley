<?php

namespace App\Services;

use App\Entity\Song;
use App\Entity\Video;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Survos\Scraper\Service\ScraperService;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Serializer\SerializerInterface;
use Yectep\PhpSpreadsheetBundle\Factory;

class AppService
{

    public function __construct(private readonly EntityManagerInterface $em,
                                private readonly SerializerInterface $serializer,
                                private readonly Factory $spreadsheet,
                                private ScraperService $scraperService,
                                private readonly LoggerInterface $logger)
    {
    }

    private function getText($elements)
    {
        $text = '';
        foreach ($elements as $element) {
            $elementClass =  $element::class;
            switch ($elementClass) {
                case PageBreak::class:
                    $text .= "\f";
                    break;
                case TextBreak::class:
                    $text .= "\n\n";
                    break;
                case TextRun::class:
                case Text::class:
                    $text .= $element->getText();
                    break;
                case Section::class:
                    $text .= $this->getText($element->getElements());
                    break;
                default:
                    dd($elementClass);
            }
            if (method_exists($element, 'getText')) {
                $text .= $element->getText();
            } else {
                if (method_exists($element, 'getElements')) {
                    $text .= $this->getText($element->getElements());
                } else {
                    // $text .= "-no-text\n";
                }
            }
        }
        return $text;
        dd($text);

    }
    public function loadLyrics(string $dir)
    {
        $finder = new Finder();
        $finder->files()->in($dir);

        foreach ($finder as $file) {
            dump($file->getFilename());
            $absoluteFilePath = $file->getRealPath();
            if (!file_exists($absoluteFilePath) || !is_readable($absoluteFilePath)) {
                throw new \Exception($absoluteFilePath . ' is not readable');
            }

            try {
//                $reader = IOFactory::load($absoluteFilePath);
//
//                $fileNameWithExtension = $file->getRelativePathname();
                $reader = IOFactory::createReader();
                $phpWord = $reader->load($absoluteFilePath);

                $sections = $phpWord->getSections();
                $text = $this->getText($sections);


                /*
                $text = '';
                foreach ($sections as $s) {
                    $els = $s->getElements();
                    foreach ($els as $e) {
                        $class = get_class($e);
                        if (method_exists($class, 'getText')) {
                            $text .= $e->getText();
                        } else {
                            dd($text, $e, $class, get_class_methods($class));
                            $text .= $e;
                        }
                    }
                }
                dd($text);
                continue;
                dd($absoluteFilePath, $phpWord);
                */

            } catch (\Exception) {
                $text = shell_exec($cmd = sprintf('catdoc "%s"', $absoluteFilePath));
                // split on formfeed
                $songs = preg_split("|\f|", $text);


                foreach ($songs as $songStr) {
                    $lines = explode("\n", (string) $songStr);
                    // remove all blank lines
                    $lines = array_filter($lines, fn($str) => !empty(trim((string) $str)));
                    // we could go through each line and see if a title matches.  For now, just use the first line as the title.
                    //
                    $title = array_shift($lines);
                    $by = array_shift($lines);

                    // find or create the song, by title
                    /** @var SongRepository $repo */
                    $repo = $this->em->getRepository(Song::class);
                    if (!$song = $repo->findOneBy(['title'=>$title])) {
                        $song = (new Song())
                            ->setTitle($title);
                        $this->em->persist($song);
                    }
                    $song->setLyrics(join("\n", $lines));
                    $this->em->flush();
                }
            }

            // dd($text, $absoluteFilePath);

            // ...
        }
    }

    public function loadSongs()
    {
        $em = null;
        /** @var Xls $readerXlsx */
        $readerXlsx  = $this->spreadsheet->createReader('Xlsx');
        /** @var Spreadsheet $spreadsheet */
        // @todo: dump to csv
        try {
            $spreadsheet = $readerXlsx->load(__DIR__ . '/../../data/kpa-songs.xlsx');
        } catch (\Exception $exception) {
            dd($exception);
        }

        $q = $this->em->createQuery(sprintf('delete from %s', Song::class));
        $numDeleted = $q->execute();

        /** @var Worksheet $sheet */
        $sheet = $spreadsheet->getActiveSheet();
        $songs = [];
        $lyrics = [];
        $header = [];

        foreach ($sheet->toArray() as $idx=>$row) {
            if ($idx === 0) {
                $header = $row;
            } else {
                $data = array_combine($header, $row);
                if (!$data['Instrumentals']) {
                    continue;
                }
                $song = (new Song())
                    ->setTitle($data['Instrumentals'])
                    ->setSchool($data['school'])
                    ->setWriters($data['writer']);

                $em = $this->em;
                $logger = $this->logger;
                $em->persist($song);
                if ($data['date']) {
                    try {
                        $song
                            ->setDate(new \DateTimeImmutable($data['date']));
                        $song->setYear((int)$song->getDate()->format('Y'));
                    } catch (\Exception) {
                        $logger->error("Line $idx: Can't set date " . $data['date'] . ' on ' . $song->getTitle());
                    }
                }
                if ($data['year']) {
                    $song
                        ->setYear((int)$data['year']);
                }

                $song->setNotes(json_encode($data, JSON_THROW_ON_ERROR));
                array_push($songs, $song);
                // dump($data);
            }
            if ($idx == 45) {
                // dd($data, $song);
                // break;
            }
        }

        $em->flush();
    }

    #[Cache(expires: 'tomorrow', public: true)]
    private function fetchUrl($url)
    {

        $client = HttpClient::create();
        $response = $client->request('GET', $url);
        switch ($response->getStatusCode()) {
            case 403:
                $errors = json_decode($response->getContent(false), FALSE, 512, JSON_THROW_ON_ERROR);
                dd($url, $errors->error);
                break;
            case 200:
                $list = $response->toArray();
                $list = json_decode(json_encode($list, JSON_THROW_ON_ERROR), FALSE, 512, JSON_THROW_ON_ERROR);
                return $list;
        }
    }

    public function testGoogleApi()
    {
        $client = new Google\Client();
        $client->setApplicationName("Client_Library_Examples");
        $client->setDeveloperKey("YOUR_APP_KEY");

        $service = new Google\Service\Books($client);
        $query = 'Henry David Thoreau';
        $optParams = [
            'filter' => 'free-ebooks',
        ];
        $results = $service->volumes->listVolumes($query, $optParams);

        foreach ($results->getItems() as $item) {
            echo $item['volumeInfo']['title'], "<br /> \n";
        }
    }
    /**
     * @return \App\Entity\Video[]
     */
    public function fetchYoutubeChannel($key, $channelId): array
    {
        // because we check by id, we don't really need this.
//        $q = $this->em->createQuery(sprintf('delete from %s', Video::class));
//        $numDeleted = $q->execute();

        $videos = [];
        $next = '';
        $repo = $this->em->getRepository(Video::class);
        do {

            $url = sprintf("https://www.googleapis.com/youtube/v3/search?part=id,snippet&type=video&maxResults=50&channelId=$channelId&type=video&key=$key&pageToken=$next");

            $results = $this->scraperService->fetchUrl($url, key: $channelId . '-x' . $next);

            $next = $results['data']['nextPageToken'] ?? false;
            foreach ($results['data']['items'] as $rawData) {
//                dump(json_encode($rawData));
                $item = (object) $rawData;
                $id = $item->id['videoId'];
                if (!$video = $repo->findOneBy(['youtubeId' => $id])) {
                    $video = (new Video())
                        ->setYoutubeId($id);
                    $this->em->persist($video);
                }
                $snippet = (object)$item->snippet;
                $raw = json_decode(json_encode($rawData), true);
                assert($raw, "Raw is null");
//                dd($raw, $snippet);
                $video
                    ->setRawData($raw)
                    ->setTitle($snippet->title)
                    ->setDescription($snippet->description);
                $video->setDate(new \DateTimeImmutable($snippet->publishedAt));

                array_push($videos, $video);
            }
        } while ($next);
        $this->em->flush();
        // dd($list);

        return $videos;

        // return $response->getContent();

    }
}
