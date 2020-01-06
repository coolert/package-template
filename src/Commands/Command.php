<?php

/*
 * This file is part of the coolert/weather.
 *
 * (c) coolert <lvhui@gmx.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Coolert\PackageTemplate\Commands;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Command
 *
 * @author coolert <lvhui@gmx.com>
 */
class Command extends BaseCommand
{
    /**
     * @var \Symfony\Component\Filesystem\Filesystem;
     */
    protected $fs;

    /**
     * @var string
     */
    protected $stemsDirectory;

    /**
     * @var array
     */
    protected $info = [
        'NAME' => '',
        'EMAIL' => '',
        'PACKAGE_NAME' => '',
        'VENDOR' => '',
        'PACKAGE' => '',
        'NAMESPACE' => '',
        'DESCRIPTION' => '',
        'PHPCS_STANDARD' => 'symfony',
    ];

    /**
     * @var string
     */
    protected $packageDirectory;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('build')
            ->setDescription('Build package')
            ->setHelp('This command allows you to build a new package template.')
            ->addArgument('directory',InputArgument::OPTIONAL,'Directory name for composer-driven project');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->stemsDirectory = __DIR__.'/../tems/';
        $git = $this->getGitGlobalConfig();
        $config = [
            'name' => 'Package Name',
            'namespace' => '',
            'phpunit' => true,
            'phpcs' => true,
            'phpcs_standards' => 'symfony',
        ];
        $helper = $this->getHelper('question');
        $question = new Question('Name of package (example: <fg=yellow>foo/bar</fg=yellow>): ');
        $question->setValidator(function ($value){
            if (trim($value) == ''){
                throw new \Exception('The package name can not be empty');
            }
            if (!preg_match('/[a-z0-9\-_]+\/[a-z0-9\-_]+/',$value)){
                throw new \Exception('Invalid package name, format: vendor/product');
            }
            return $value;
        });
        $question->setMaxAttempts(10);

        // package name
        $this->info['PACKAGE_NAME'] = $helper->ask($input,$output,$question);

        // vendor/namespace
        $defaultNamespace = implode('\\', array_map([$this,'studlyCase'], explode('/',$this->info['PACKAGE_NAME'])));
        $question = new Question("Namespace of package [<fg=yellow>{$defaultNamespace}</fg=yellow>]: ",$defaultNamespace);
        $this->info['NAMESPACE'] = $helper->ask($input,$output,$question);
        $this->info['VENDOR'] = strtolower(strstr($this->info['PACKAGE_NAME'],'/',true));
        $this->info['PACKAGE'] = substr($this->info['PACKAGE_NAME'],strlen($this->info['VENDOR']) + 1);

        // description
        $question = new Question('Description of package: ');
        $this->info['DESCRIPTION'] = $helper->ask($input,$output,$question);

        // name
        $question = new Question(sprintf('Author name of package [<fg=yellow>%s</fg=yellow>]',$git['user.name'] ?? $this->info['VENDOR']),$git['user.name'] ?? $this->info['VENDOR']);
        $this->info['NAME'] = $helper->ask($input,$output,$question);

        // email
        if (!empty($git['user.email'])){
            $question = new Question(sprintf('Author email of package [<fg=yellow>%s</fg=yellow>]:',$git['user.email']),$git['user.email']);
        }else{
            $question = new Question('Author email of package ?');
        }
        $this->info['EMAIL'] = $helper->ask($input,$output,$question);

        // license
        $question = new Question('license of package [<fg=yellow>MIT</fg=yellow>]','MIT');
        $this->info['LICENSE'] = $helper->ask($input,$output,$question);

        // test?
        $question = new ConfirmationQuestion('Do you want to test this package ? [<fg=yellow>Y/n</fg=yellow>]: ', 'yes');
        $config['phpunit'] = $helper->ask($input,$output,$question);

        //phpcs
        $question = new  ConfirmationQuestion('Do you want to use php-cs-fixer format your code ? [<fg=yellow>Y/n</fg=yellow>]','yes');
        $config['phpcs'] = $helper->ask($input,$output,$question);
        if ($config['phpcs']){
            $question = new Question('Standard name of php-cs-fixer [<fg=yellow>symfony</fg=yellow>]: ','symfony');
            $this->info['PHPCS_STANDARD'] = ucfirst(strtolower($helper->ask($input,$output,$question)));
        }
        $directory = './'.$input->getArgument('directory');
        $this->packageDirectory = $directory;

        $this->createPackage($config);
        $this->initComposer();
        $this->setNamespace();

        $output->writeln(\sprintf('<info>Package %s created in: </info><comment>%s</comment>', $this->info['PACKAGE_NAME'], $directory));
    }

    /**
     * @param string $value
     * @return mixed
     */
    protected function studlyCase($value)
    {
        return str_replace(' ', '', ucwords(str_replace(['_','-'], ' ', $value)));
    }

    /**
     * Get git config
     *
     * @return array
     */
    protected function getGitGlobalConfig()
    {
        $config = [];
        try {
            $lines = preg_split("/\n[\r]?/",trim(shell_exec('git config --list --global')));
            foreach ($lines as $line){
                list($k,$v) = array_pad(explode('=',$line),2,null);
                $config[$k] = $v;
            }
        }catch (\Exception $e){
            //
        }
        return $config;
    }

    /**
     * Create package directory and base files.
     *
     * @param array $config
     */
    protected function createPackage(array $config)
    {
        $this->fs->mkdir($this->packageDirectory.'/src/',0755);
        $this->fs->touch($this->packageDirectory.'/src/.gitkeep');
        $this->copyFile('editorconfig','.editorconfig');
        $this->copyFile('gitattributes','.gitattributes');
        $this->copyFile('gitignore','.gitignore');
        if ($config['phpcs']){
            $this->copyFile('php_cs','.php_cs');
        }
        if ($config['phpunit']){
            $this->createPHPUnitFile();
        }
        $this->copyFile('README.md');
    }

    /**
     * Create PHPUnit files
     */
    protected function createPHPUnitFile()
    {
        $this->fs->dumpFile($this->packageDirectory.'/tests/.gitkeep','');
        $this->copyFile('phpunit_config','phpunit.xml.dist');
    }

    /**
     * Copy file
     *
     * @param string $file
     * @param string $filename
     */
    protected function copyFile(string $file,string $filename = '')
    {
        $target = $this->packageDirectory.'/'.($filename ?: $file);
        $content = str_replace(array_keys($this->info), array_values($this->info), file_get_contents($this->stemsDirectory.$file));
        $this->fs->dumpFile($target,$content);
    }

    /**
     * Init composer command
     */
    protected function initComposer()
    {
        $author = !empty($this->info['EMAIL']) ? sprintf('--author "%s <%s>"', $this->info['NAME'] ?? 'yourname', $this->info['EMAIL'] ?? 'you@example.com') : '';
        exec(sprintf(
            'composer init --no-interaction --name "%s" %s --description "%s" --license %s --working-dir %s',
            $this->info['PACKAGE_NAME'],
            $author,
            $this->info['DESCRIPTION'] ?? 'Package description here.',
            $this->info['LICENSE'],
            $this->packageDirectory
        ));
    }

    protected function setNamespace()
    {
        $composerJson = $this->packageDirectory.'/composer.json';
        $composer = \json_decode(\file_get_contents($composerJson));

        $composer->autoload = [
            'psr-4' => [
                $this->info['NAMESPACE'].'\\' => 'src',
            ],
        ];

        \file_put_contents($composerJson, \json_encode($composer, \JSON_PRETTY_PRINT|\JSON_UNESCAPED_UNICODE));
    }
}