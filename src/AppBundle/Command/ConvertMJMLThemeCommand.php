<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace AppBundle\Command;

use AppBundle\Exception\NotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConvertMJMLThemeCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'prestashop:mail:convert-mjml';

    protected function configure()
    {
        $this
            ->setDescription('Convert an MJML theme to a twig theme')
            ->addArgument('mjmlThemePath', InputArgument::REQUIRED, 'MJML theme path to convert.')
            ->addArgument('twigThemePath', InputArgument::REQUIRED, 'Target twig theme path where files are converted.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mjmlThemePath = $input->getArgument('mjmlTheme');
        $twigThemePath = $input->getArgument('twigTheme');

        if (!is_dir($mjmlThemePath)) {
            throw new NotFoundException(sprintf('Could not find mjml theme folder %s', $mjmlThemePath));
        }
        if (!is_dir($twigThemePath)) {
            throw new NotFoundException(sprintf('Could not find twig theme folder %s', $twigThemePath));
        }

        /** @var TwigTemplateConverter $converter */
        $converter = $this->getContainer()->get('prestashop.adapter.mail_template.mjml.twig_template_converter');

        $fileSystem = new Filesystem();
        $finder = new Finder();
        $finder->files()->name('*.mjml.twig')->in($mjmlThemeFolder);
        /** @var SplFileInfo $mjmlFile */
        foreach ($finder as $mjmlFile) {
            //Ignore components file for now
            if (preg_match('/^components/', $mjmlFile->getRelativePathname())) {
                if ('components/layout.mjml.twig' == $mjmlFile->getRelativePathname()) {
                    $output->writeln('Converting layout '.$mjmlFile->getRelativePathname());
                    $twigTemplate = $converter->convertLayoutTemplate($mjmlFile->getRealPath(), $mjmlTheme, $twigTheme);
                } else {
                    $output->writeln('Converting component '.$mjmlFile->getRelativePathname());
                    $twigTemplate = $converter->convertComponentTemplate($mjmlFile->getRealPath(), $mjmlTheme);
                }
            } else {
                $output->writeln('Converting template '.$mjmlFile->getRelativePathname());
                $twigTemplate = $converter->convertChildTemplate($mjmlFile->getRealPath(), $twigTheme);
            }

            $twigTemplatePath = $mailThemesDir.'/'.$twigTheme.'/'.$mjmlFile->getRelativePathname();
            $twigTemplatePath = preg_replace('/mjml\.twig/', 'html.twig', $twigTemplatePath);
            $twigTemplateFolder = dirname($twigTemplatePath);
            if (!$fileSystem->exists($twigTemplateFolder)) {
                $fileSystem->mkdir($twigTemplateFolder);
            }

            file_put_contents($twigTemplatePath, $twigTemplate);
        }
    }
}