<?php

namespace App\Tests\Load;

use App\Command\LoadProductsCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Console\Test\InteractsWithConsole;

class AppLoadDataCommandTest extends KernelTestCase
{
    use InteractsWithConsole;

    #[Test]
    public function load(): void
    {
        $this->executeConsoleCommand('app:load')
            ->assertSuccessful() // command exit code is 0
            ->assertOutputContains('3 products loaded')
            ->assertOutputNotContains('failed')
        ;

        // advanced usage
        if (0)
        $this->consoleCommand(LoadProductsCommand::class) // can use the command class or "name"
        ->splitOutputStreams() // by default stdout/stderr are combined, this options splits them
        ->addArgument('kbond')
            ->addOption('--admin') // with or without "--" prefix
            ->addOption('role', ['ROLE_EMPLOYEE', 'ROLE_MANAGER'])
            ->addOption('-R') // shortcut options require the "-" prefix
            ->addOption('-vv') // by default, output has normal verbosity, use the standard options to change (-q, -v, -vv, -vvv)
            ->addOption('--ansi') // by default, output is undecorated, use this option to decorate
            ->execute() // run the command
            ->assertSuccessful()
            ->assertStatusCode(0) // equivalent to ->assertSuccessful()
            ->assertOutputContains('Creating admin user "kbond"')
            ->assertErrorOutputContains('this is in stderr') // used in conjunction with ->splitOutputStreams()
            ->assertErrorOutputNotContains('admin user') // used in conjunction with ->splitOutputStreams()
            ->dump() // dump() the status code/outputs and continue
            ->dd() // dd() the status code/outputs
        ;

    }
}
