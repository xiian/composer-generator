<?php

namespace xiian\ComposerGenerator\Command;

use Composer\Command\BaseCommand;
use Composer\Command\InitCommand;
use Composer\Command\InstallCommand;
use Composer\Json\JsonManipulator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use xiian\ComposerGenerator\Skeleton\PackageGenerator;

class GenerateCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('generate')
            ->addOption('vendor', null, InputOption::VALUE_REQUIRED, 'Vendor name', 'xiian')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Package name', 'composer-generator')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Description', 'Generate composer packages adhering to certain standards and conventions.')
            ->addOption('author_name', null, InputOption::VALUE_REQUIRED, 'Author Name', 'Tom Sartain')
            ->addOption('author_email', null, InputOption::VALUE_REQUIRED, 'Author Email', 'tomsartain@gmail.com')
            ->addOption('base_namespace', null, InputOption::VALUE_REQUIRED, 'Base Namespace', 'xiian\\ComposerGenerator')
            ->addOption('project_name', null, InputOption::VALUE_REQUIRED, 'Project Name', 'Composer Generator')
            ->addOption('github_url', null, InputOption::VALUE_REQUIRED, 'Project Name', 'git@github.com:xiian/composer-generator.git')
            ->setDescription('Generate a composer package adhering to certain standards and conventions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Generating a templated package');

        foreach (['vendor', 'name', 'description', 'author_name', 'author_email', 'base_namespace', 'project_name', 'github_url'] as $v) {
            $output->writeln(sprintf('%20s : %s', $v, $input->getOption($v)));
        }

        $requirements     = [];
        $requirements_dev = [
            'phpunit/phpunit:~4.1',
            'liip/RMT',
            'mockery/mockery',
            'pds/skeleton',
        ];

        $output->writeln('Making a composer package');
        $composer_output = new BufferedOutput();
        /** @var InitCommand $command */
        $command = $this->getApplication()->get('init');
        $command->run(
            new ArrayInput(
                [
                    '--name'        => implode('/', [$input->getOption('vendor'), $input->getOption('name')]),
                    '--description' => $input->getOption('description'),
                    '--author'      => sprintf('%s <%s>', $input->getOption('author_name'), $input->getOption('author_email')),
                    '--require'     => $requirements,
                    '--require-dev' => $requirements_dev,
                ]
            ),
            $composer_output
        );

        // Add more stuff to the composer.json (doing this stupid for now)
        $manip = new JsonManipulator(file_get_contents('composer.json'));

        // Add autoloading
        $manip->addSubNode(
            'autoload',
            'psr-4',
            [
                rtrim($input->getOption('base_namespace'), '\\') . '\\' => 'src/',
            ]
        );
        // Add autoloading for tests
        $manip->addSubNode(
            'autoload-dev',
            'psr-4',
            [
                rtrim($input->getOption('base_namespace'), '\\') . '\\test\\' => 'Test/',
            ]
        );
        // Add scripts
        $manip->addSubNode('scripts', 'clean', 'rm -rf build docs');
        $manip->addSubNode('scripts', 'build-prepare', 'mkdir build docs');
        $manip->addSubNode('scripts', 'build-phploc', 'phploc --log-xml=build/phploc.xml src/');
        $manip->addSubNode('scripts', 'build-phpcs', 'phpcs src/ --report-xml=build/phpcs.xml --report-checkstyle=build/checkstyle.xml || true');
        $manip->addSubNode('scripts', 'static-analysis', ['@build-phploc', '@build-phpcs']);
        $manip->addSubNode('scripts', 'build-phpunit', 'phpunit');
        $manip->addSubNode('scripts', 'test', ['@build-phpunit']);
        $manip->addSubNode('scripts', 'build-phpdox', 'phpdox');
        $manip->addSubNode('scripts', 'docs', ['@build-phpdox']);
        $manip->addSubNode('scripts', 'build-all', ['@clean', '@build-prepare', '@static-analysis', '@test', '@docs']);

        file_put_contents('composer.json', $manip->getContents());

        $output->writeln('Installing composer packages');
        /** @var InstallCommand $command */
        $command = $this->getApplication()->get('install');
        $command->run(
            new ArrayInput(
                [

                ]
            ),
            $composer_output
        );

        $output->writeln('Generate the PDS Skeleton');
        // Uses a custom PackageGenerator to get rid of some unneeded things
        $generator = new PackageGenerator();
        $generator->execute(getcwd());

        $output->writeln('Copying over some templated files');
        $match_map = [
            ':PROJECT_NAME'        => $input->getOption('project_name'),
            ':PROJECT_DESCRIPTION' => $input->getOption('description'),
        ];
        $search    = array_keys($match_map);
        $replace   = array_values($match_map);

        foreach (['README.md', 'phpdox.xml', 'phpunit.xml', '.rmt.yml'] as $file) {
            $contents = file_get_contents(__DIR__ . '/../../resources/template.' . $file);
            $contents = str_replace($search, $replace, $contents);
            $contents = preg_replace_callback(
                '~:(PROJECT_NAME)~',
                function ($match) use ($match_map) {
                    return $match_map[$match];
                },
                $contents
            );
            file_put_contents($file, $contents);
        }

        $output->writeln('Do git stuff');
//        $output->writeln('Initializing git repo');
//        $output->writeln('Set up remote repo');
//        $output->writeln('Do the .gitignore');
//        $output->writeln('Commit everything');
//        $output->writeln('Push up to remote');
    }

}


