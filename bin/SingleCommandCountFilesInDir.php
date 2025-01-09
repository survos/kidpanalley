    #!/usr/bin/env php
    <?php
    require __DIR__.'/../vendor/autoload.php';

    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\SingleCommandApplication;

    (new SingleCommandApplication())
        ->setName('File Counter') // Optional
        ->setVersion('1.0.0') // Optional
        ->addArgument('dir', InputArgument::OPTIONAL, 'The directory', default: '.')
        ->addOption('all', 'a', InputOption::VALUE_NONE, 'count all files')
        ->setCode(function (InputInterface $input, OutputInterface $output): int {
            $dir = realpath($input->getArgument('dir'));
            $all = $input->getOption('all');
            $finder = (new Symfony\Component\Finder\Finder())
                ->in($dir)
                ->files()
                ->ignoreVCSIgnored(!$all)
            ;
            $count = iterator_count($finder);
            $output->writeln( "$dir has $count " .
                ($all ? "files" : "files in source control"));
            return SingleCommandApplication::SUCCESS;
        })
        ->run();