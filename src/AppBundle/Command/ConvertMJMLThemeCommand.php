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

use AppBundle\Converter\TwigTemplateConverter;
use AppBundle\Exception\FileNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ConvertMJMLThemeCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'prestashop:mail:convert-mjml';

    /** @var TwigTemplateConverter */
    private $converter;

    /** @var string */
    private $mjmlMailThemesDir;

    /**
     * @param TwigTemplateConverter $converter
     * @param string $mjmlMailThemesDir
     */
    public function __construct(TwigTemplateConverter $converter, $mjmlMailThemesDir)
    {
        parent::__construct(self::$defaultName);
        $this->converter = $converter;
        $this->mjmlMailThemesDir = $mjmlMailThemesDir;
    }

    protected function configure()
    {
        $this
            ->setDescription('Convert an MJML theme to a twig theme')
            ->addArgument('mjmlTheme', InputArgument::REQUIRED, 'MJML theme to convert.')
            ->addArgument('twigThemePath', InputArgument::REQUIRED, 'Target twig theme path where files are converted.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mjmlTheme = $input->getArgument('mjmlTheme');
        $twigThemePath = $input->getArgument('twigThemePath');

        $mjmlThemeFolder = $this->mjmlMailThemesDir.'/'.$mjmlTheme;
        if (!is_dir($mjmlThemeFolder)) {
            throw new FileNotFoundException(sprintf('Could not find mjml theme folder %s', $mjmlThemeFolder));
        }
        if (!is_dir($twigThemePath)) {
            throw new FileNotFoundException(sprintf('Could not find twig theme folder %s', $twigThemePath));
        }
        $twigThemePath = realpath($twigThemePath);
        $twigTheme = basename($twigThemePath);

        $fileSystem = new Filesystem();
        $finder = new Finder();
        $finder->files()->sort(function ($a, $b) {
            return $b->getMTime() - $a->getMTime();
        })->name('*.mjml.twig')->in($mjmlThemeFolder);

        /** @var SplFileInfo $mjmlFile */
        foreach ($finder as $mjmlFile) {
            //Ignore components file for now
            $path_separator = preg_match('#/#', $mjmlFile->getRelativePathname()) ? '/' : '\\';
            if (preg_match('/^components/', $mjmlFile->getRelativePathname())) {
                if ('components' . $path_separator . 'layout.mjml.twig' == $mjmlFile->getRelativePathname() ||
                    'components' . $path_separator . 'order_layout.mjml.twig' == $mjmlFile->getRelativePathname()) {
                    $output->writeln('Converting layout '.$mjmlFile->getRelativePathname());
                    $twigTemplate = $this->converter->convertLayoutTemplate($mjmlFile->getRealPath(), $mjmlTheme, $twigTheme);
                } else {
                    $isWrapped = 'components' . $path_separator . 'footer.mjml.twig' !== $mjmlFile->getRelativePathname();
                    $output->writeln('Converting component '.$mjmlFile->getRelativePathname());
                    $twigTemplate = $this->converter->convertComponentTemplate($mjmlFile->getRealPath(), $mjmlTheme, $twigTheme, $isWrapped);
                }
            } else {
                $output->writeln('Converting template '.$mjmlFile->getRelativePathname());
                $twigTemplate = $this->converter->convertChildTemplate($mjmlFile->getRealPath(), $mjmlTheme, $twigTheme);
            }

            $twigTemplatePath = $twigThemePath.'/'.$mjmlFile->getRelativePathname();
            $twigTemplatePath = preg_replace('/mjml\.twig/', 'html.twig', $twigTemplatePath);
            $twigTemplateFolder = dirname($twigTemplatePath);
            if (!$fileSystem->exists($twigTemplateFolder)) {
                $fileSystem->mkdir($twigTemplateFolder);
            }

            file_put_contents($twigTemplatePath, $twigTemplate);
        }

        $output->writeln('Copying assets');
        $assetsFolder = $mjmlThemeFolder.'/assets';
        $twigAssetsFolder = $twigThemePath.'/assets';
        if (!$fileSystem->exists($twigAssetsFolder)) {
            $fileSystem->mkdir($twigAssetsFolder);
        }

        $finder = new Finder();
        $finder->files()->in($assetsFolder);
        /** @var SplFileInfo $assetFile */
        foreach ($finder as $assetFile) {
            $twigAssetPath = $twigAssetsFolder.'/'.$assetFile->getRelativePathname();
            $output->writeln('Copying asset '.$twigAssetPath);
            $fileSystem->copy($assetFile->getRealPath(), $twigAssetPath);
        }
    }
}
