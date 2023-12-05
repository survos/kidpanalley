<?php

namespace App\Services;

use App\Entity\Song;
use App\Entity\Video;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use League\Flysystem\DirectoryListing;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use Psr\Log\LoggerInterface;
use Survos\Scraper\Service\ScraperService;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;

class AppService
{

    public function __construct(private readonly EntityManagerInterface $em,
                                private readonly SerializerInterface    $serializer,
                                private SongRepository                  $songRepository,
                                private ScraperService                  $scraperService,
                                private ValidatorInterface $validator,
                                private readonly LoggerInterface        $logger,
                                private array $songs = [],

    )
    {
    }

    private function getText($elements)
    {
        $text = '';
        foreach ($elements as $element) {
            $elementClass = $element::class;
            switch ($elementClass) {
                case PageBreak::class:
                    $text .= "\f";
                    break;
                case TextBreak::class:
                    $text .= "\n\n";
                    break;
//                    $text = $element->getText();
//                    $text = '??';
                    break;
                case TextRun::class:
                case Text::class:
                    $text .= $element->getText();
                    break;
                case Title::class:
                    $titleElementText = $element->getText();
                    if (is_string($titleElementText)) {
                        $text .= $titleElementText;
                    } else {
                        if ($titleElementText::class == TextRun::class) {
//                            $text .= $this->getText($titleElementText->getElements());
                        } else {
                            dd($titleElementText);
                        }
                    }
                    break;
                case Section::class:
                    $text .= $this->getText($element->getElements());
                    break;
                default:
                    dd($elementClass);
            }

            if (method_exists($element, 'getElements')) {
                $text .= $this->getText($element->getElements());
            } else {
                if (method_exists($element, 'getText')) {
//                    $text .= $element->getText();
                } else {
                }
                // $text .= "-no-text\n";
            }
        }
        return $text;
        dd($text);

    }

    public function loadLyrics(string $dir)
    {

        $client = new Client($appSecret);

        $adapter = new DropboxAdapter($client);

        $filesystem = new Filesystem($adapter, ['case_sensitive' => false]);
        $filesystem->write('/', );
        dd ($filesystem->listContents('/', $filesystem::LIST_DEEP));


        $finder = new Finder();
        $finder->files()->in($dir)->name('*.doc*');

        foreach ($finder as $idx => $file) {
            $absoluteFilePath = $file->getRealPath();
            if (!file_exists($absoluteFilePath) || !is_readable($absoluteFilePath)) {
                throw new \Exception($absoluteFilePath . ' is not readable');
            }

            if ($file->getExtension() == 'doc') {
                $title = $file->getFilenameWithoutExtension();
                // some songs are repeated by multiple schools, e.g. I used to know the names of all the stars
                foreach ($this->songRepository->findBy(['title' => $title]) as $song) {
                    // yay!
                    $process = new Process(['catdoc', $file->getRealPath()]);
                    $process->run();
// executes after the command finishes
                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }
                    $text = $process->getOutput();
                    $text = str_replace("\n\n", "\n", $text);

//                    $text = shell_exec($cmd = sprintf('catdoc "%s"', $absoluteFilePath));
                    // split on formfeed
                    $theseSongs = preg_split("|\f|", $text);


                    foreach ($theseSongs as $songStr) {
                        $lines = explode("\n", (string)$songStr);
                        // remove all blank lines
                        $lines = array_filter($lines, fn($str) => !empty(trim((string)$str)));
                        // we could go through each line and see if a title matches.  For now, just use the first line as the title.
                        //
                        $title = array_shift($lines);
                        $by = array_shift($lines);

                        // find or create the song, by title
                        /** @var SongRepository $repo */
                        $repo = $this->em->getRepository(Song::class);
                        if (!$title) {
                            continue;
                        }
                        if (!$song = $repo->findOneBy(['title' => $title])) {
                            $code = Song::createCode($title);
                            assert($code, $title);
                                $song = (new Song($code));
                                $this->em->persist($song);
                                $songs[$code] = $song;
                            $song
                                ->setTitle($title);
                            $this->em->persist($song);
                        }
                        $song->setLyrics(join("\n", $lines));
//                        dd($song->getLyrics(), $song->getId());
                    }
                    $song->setLyrics($text);
                    if ($errors = $this->validator->validate($song)->count()) {
                        assert(false, (string)$errors);
                    }

                }

            } else {
                $reader = IOFactory::createReader();
                try {
                    $phpWord = $reader->load($absoluteFilePath);
                } catch (\Exception $exception) {
                    dd($exception, $absoluteFilePath);
                }

                $sections = $phpWord->getSections();
                $text = $this->getText($sections);
                $text = str_replace("\n\n", "\n", $text);
                if (!$song = $this->songRepository->findOneBy(['title' => $title])) {
                    $code = 'file_' . md5($title);
                    if (!$song = $this->songs[$code]??null) {
                        $song = (new Song($code))
                            ->setTitle($title);
                        $this->em->persist($song);
                    }
                    $this->songs[$code] = $song;
                }
                $song->setLyrics($text);
            }
        }
        $this->em->flush();
    }

    public function loadExistingSongs()
    {
        foreach ($this->songRepository->findAll() as $song) {
            $this->songs[$song->getCode()] = $song;
        }

    }
    public function loadSongs()
    {
        // in2csv kpa-songs.xlsx  > kpa-songs.csv
        $songsCsv = __DIR__ . '/../../data/kpa-songs.csv';
        $reader = Reader::createFromPath($songsCsv, 'r');
        $reader->setHeaderOffset(0);
        $records = $reader->getRecords();


//        $q = $this->em->createQuery(sprintf('delete from %s', Song::class));
//        $numDeleted = $q->execute();

        foreach ($records as $idx => $data) {
            if (!$data['Instrumentals']) {
                continue;
            }

            $code = Song::createCode($title = $data['Instrumentals'], $school = $data['school'], $year = $data['year']);
//            $songDate = new \DateTimeImmutable($data['date']);
            $year =  $data['year']??null;

            if (!$song = $this->songs[$code]??null) {
                $song = (new Song($code));
                $this->em->persist($song);
                $this->songs[$code] = $song;
            }
            $song
                ->setTitle($title)
                ->setSchool($school)
                ->setWriters($data['writer']);

//            if ($song->getDate()) {
//                try {
//                    $song->setYear((int)$song->getDate()->format('Y'));
//                } catch (\Exception) {
//                    $logger->error("Line $idx: Can't set date " . $data['date'] . ' on ' . $song->getTitle());
//                }
//            }
            if ($data['year']) {
                $song
                    ->setYear((int)$year);
            }

            $song->setNotes(json_encode($data, JSON_THROW_ON_ERROR));
            if ($idx == 45) {
                // dd($data, $song);
                // break;
            }
        }

        $this->em->flush();
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
                $item = (object)$rawData;
                $id = $item->id['videoId'];
                if (!$video = $repo->findOneBy(['youtubeId' => $id])) {
                    $video = (new Video())
                        ->setYoutubeId($id);
                    $this->em->persist($video);
                }

                $snippet = (object)$item->snippet;
                $title = $snippet->title;
                // song needs to be school + title, as does the video
                if (!$song = $this->songRepository->findOneBy(['title' => $title])) {
                    $song = (new Song($id))
                        ->setTitle($title);
                    // @todo: parse out stuff to get the title
                    $this->em->persist($song);
                }
                $this->logger->warning("Adding video to song " . $song->getTitle(), ['id' => $video->getYoutubeId()]);
                $song->addVideo($video);

                $raw = json_decode(json_encode($rawData), true);
                assert($raw, "Raw is null");
//                dd($raw, $snippet);
                $video
                    ->setThumbnailUrl($snippet->thumbnails['default']['url'])
                    ->setRawData($raw)
                    ->setTitle($snippet->title)
                    ->setDescription($snippet->description);
                $video->setDate(new \DateTimeImmutable($snippet->publishedAt));

                array_push($videos, $video);
            }
            $this->em->flush();
        } while ($next);
        // dd($list);

        return $videos;

        // return $response->getContent();

    }
}
