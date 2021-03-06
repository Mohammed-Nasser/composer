<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Command;

use Composer\DependencyResolver\Pool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Justin Rainbow <justin.rainbow@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DependsCommand extends Command
{
    protected $linkTypes = array(
        'require' => array('requires', 'requires'),
        'require-dev' => array('devRequires', 'requires (dev)'),
    );

    protected function configure()
    {
        $this
            ->setName('depends')
            ->setDescription('Shows which packages depend on the given package')
            ->setDefinition(array(
                new InputArgument('package', InputArgument::REQUIRED, 'Package to inspect'),
                new InputOption('link-type', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Link types to show (require, require-dev)', array_keys($this->linkTypes)),
            ))
            ->setHelp(<<<EOT
Displays detailed information about where a package is referenced.

<info>php composer.phar depends composer/composer</info>

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repo = $this->getComposer()->getRepositoryManager()->getLocalRepository();
        $needle = $input->getArgument('package');

        $pool = new Pool();
        $pool->addRepository($repo);

        $packages = $pool->whatProvides($needle);
        if (empty($packages)) {
            throw new \InvalidArgumentException('Could not find package "'.$needle.'" in your project.');
        }

        $linkTypes = $this->linkTypes;

        $types = array_map(function ($type) use ($linkTypes) {
            $type = rtrim($type, 's');
            if (!isset($linkTypes[$type])) {
                throw new \InvalidArgumentException('Unexpected link type: '.$type.', valid types: '.implode(', ', array_keys($linkTypes)));
            }

            return $type;
        }, $input->getOption('link-type'));

        $messages = array();
        $repo->filterPackages(function ($package) use ($needle, $types, $linkTypes, &$messages) {
            static $outputPackages = array();

            foreach ($types as $type) {
                foreach ($package->{'get'.$linkTypes[$type][0]}() as $link) {
                    if ($link->getTarget() === $needle) {
                        if (!isset($outputPackages[$package->getName()])) {
                            $messages[] = '<info>'.$package->getPrettyName() . '</info> ' . $linkTypes[$type][1] . ' ' . $needle .' (<info>' . $link->getPrettyConstraint() . '</info>)';
                            $outputPackages[$package->getName()] = true;
                        }
                    }
                }
            }
        });

        if ($messages) {
            sort($messages);
            $output->writeln($messages);
        } else {
            $output->writeln('<info>There is no installed package depending on "'.$needle.'".</info>');
        }
    }
}
