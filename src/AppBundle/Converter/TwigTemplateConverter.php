<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
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
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace AppBundle\Converter;

use AppBundle\Exception\FileNotFoundException;
use AppBundle\Exception\InvalidArgumentException;
use AppBundle\Mjml\MjmlConverter;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use DOMDocument;
use DOMElement;

class TwigTemplateConverter
{
    /** @var TwigEngine */
    private $engine;

    /** @var MjmlConverter */
    private $mjmlConverter;

    /** @var string */
    private $tempDir;

    /** @var Filesystem */
    private $fileSystem;

    /** @var string */
    private $templateContent;

    /**
     * @param TwigEngine $engine
     * @param MjmlConverter $mjmlConverter
     * @param string $tempDir
     */
    public function __construct(
        TwigEngine $engine,
        MjmlConverter $mjmlConverter,
        $tempDir = ''
    ) {
        if (!$engine instanceof TwigEngine) {
            throw new InvalidArgumentException('The required engine must be a TwigEngine');
        }
        $this->engine = $engine;
        $this->mjmlConverter = $mjmlConverter;
        $this->tempDir = empty($tempDir) ? sys_get_temp_dir() . '/mjml_twig_converter' : $tempDir;
        $this->fileSystem = new Filesystem();
    }

    public function convertLayoutTemplate($mjmlTemplatePath, $mjmlTheme, $newTheme)
    {
        if (!file_exists($mjmlTemplatePath)) {
            throw new FileNotFoundException(sprintf('Could not find mjml template %s', $mjmlTemplatePath));
        }

        $this->templateContent = file_get_contents($mjmlTemplatePath);

        //Replace theme and include files extensions
        $this->templateContent = preg_replace('#/'.$mjmlTheme.'/#', '/'.$newTheme.'/', $this->templateContent);
        $this->templateContent = preg_replace('#mjml\.twig#', 'html.twig', $this->templateContent);

        //This block doesn't need mj-raw as it is in a mj-title tag so we temporarily reformat it
        $this->templateContent = preg_replace('#{% block title %}(.*){% endblock %}#', '%%title \1%%', $this->templateContent);
        //All twig tag must be included in mj-raw tags
        $this->templateContent = preg_replace('#{% (.*?) %}#', '<mj-raw>%% \1 %%</mj-raw>', $this->templateContent);

        $twigLayout = $this->mjmlConverter->convert($this->templateContent);

        //Transform back twig blocks
        $twigLayout = preg_replace('#%% (.*?) %%#', '{% \1 %}', $twigLayout);

        //Add EOL and indentation for clarity
        $twigLayout = preg_replace('/{% block (.*?) %}/', "\n                  {% block \\1 %}\n", $twigLayout);
        $twigLayout = preg_replace('/{% include \'@MjmlMailThemes(.*?) %}/', "                      {% include '@MailThemes\\1 %}\n", $twigLayout);
        $twigLayout = preg_replace('/{% endblock %}/', "                  {% endblock %}\n", $twigLayout);

        //Transform back title block
        $twigLayout = preg_replace('#%%title (.*)%%#', '{% block title %}\1{% endblock %}', $twigLayout);

        //Add the styles block in the header
        $dom = new DOMDocument();
        $dom->loadHTML($twigLayout);
        $blockStyleStart = $dom->createTextNode("{% block styles %}\n  ");
        $blockStyleEnd = $dom->createTextNode("  {% endblock %}\n");
        /** @var DOMElement $head */
        $head = $dom->getElementsByTagName('head')->item(0);
        /** @var DOMElement $style First style tag in head */
        $style = $head->getElementsByTagName('style')->item(0);
        $head->insertBefore($blockStyleStart, $style);
        $head->appendChild($blockStyleEnd);

        //Add inline CSS for RTL languages
        $link = $dom->createTextNode("{% if languageIsRTL %}\n  ".
            "<style type=\"text/css\">{% include '@MailThemes/modern/assets/rtl.css' %}</style>\n  ".
            "{% endif %}\n  ");
        $head->insertBefore($link, $blockStyleStart);
        
        $html = $dom->saveHTML();

        // Since DOMDocument::saveHTML converts special characters into special HTML characters we revert them back
        $html = htmlspecialchars_decode($html);

        return $html;
    }

    public function convertComponentTemplate($mjmlTemplatePath, $mjmlTheme, $newTheme, bool $isWrapped)
    {
        if (!file_exists($mjmlTemplatePath)) {
            throw new FileNotFoundException(sprintf('Could not find mjml template %s', $mjmlTemplatePath));
        }

        $this->templateContent = file_get_contents($mjmlTemplatePath);
        $templateName = basename($mjmlTemplatePath);
        $this->templateContent = "{% extends '@MjmlMailThemes/".$mjmlTheme."/components/layout.mjml.twig' %}

{% block content %}
$this->templateContent
{% endblock %}

{% block header %}{% endblock %}
{% block footer %}{% endblock %}
";

        $convertedLayout = $this->convertLayout($mjmlTheme, $newTheme, $isWrapped);

        return $convertedLayout['content'];
    }

    public function convertChildTemplate($mjmlTemplatePath, $mjmlTheme, $newTheme)
    {
        if (!file_exists($mjmlTemplatePath)) {
            throw new FileNotFoundException(sprintf('Could not find mjml template %s', $mjmlTemplatePath));
        }

        $this->templateContent = file_get_contents($mjmlTemplatePath);

        $this->templateContent .= "
        {% block header %}{% endblock %}
        {% block footer %}{% endblock %}
";

        //Replace parent layout with conversion layout
        $mjmlLayout = $this->getParentLayout();
        $twigLayout = $this->convertTwigLayoutPath($mjmlLayout, $newTheme);
        $layoutTile = $this->getLayoutTitle();

        $convertedLayout = $this->convertLayout($mjmlTheme, $newTheme, true);
        $layoutContent = $convertedLayout['content'];
        $layoutStyles = $convertedLayout['styles'];

        return "{% extends $twigLayout %}
        
{% block title %}$layoutTile{% endblock %}

{% block content %}
$layoutContent
{% endblock %}

{% block styles %}
$layoutStyles
{% endblock %}
";
    }

    /**
     * @param string $mjmlTheme
     * @param string $newTheme
     *
     * @return array
     * @throws \Twig\Error\Error
     */
    private function convertLayout($mjmlTheme, $newTheme, bool $isWrapped)
    {
        $convertedTemplate = $this->convertMjml($this->templateContent);

        //MJML returns a full html template, get only the body content
        $innerHtml = $this->extractHtml($convertedTemplate, '.wrapper-container table tr td', 0);

        //Add a few EOL for clarity
        $innerHtml = preg_replace('/{% extends (.*?) %}/', "{% extends \\1 %}\n\n", $innerHtml);
        $innerHtml = preg_replace('/{% block (.*?) %}/', "{% block \\1 %}\n", $innerHtml);
        $innerHtml = preg_replace('/{% endblock %}/', "\n{% endblock %}\n\n", $innerHtml);
        $innerHtml = trim($innerHtml)."\n";

        //Link href and img src are wrongly escaped
        $innerHtml = preg_replace('/href="%7B(.*?)%7D"/', 'href="{\1}"', $innerHtml);
        $innerHtml = preg_replace('/src="%7B(.*?)%7D"/', 'src="{\1}"', $innerHtml);
        $innerHtml = preg_replace('/href="%7B%7B%20(.*?)%20%7D%7D/', 'href="{{ \1 }}', $innerHtml);
        $innerHtml = preg_replace('/src="%7B%7B%20(.*?)%20%7D%7D/', 'src="{{ \1 }}', $innerHtml);

        //Update assets path
        $innerHtml = preg_replace('#'.$mjmlTheme.'/assets/#', $newTheme.'/assets/', $innerHtml);

        //if mj-section is inside mj-wrapper, we need to remove the conditional `if mso <table>`
        //if mj-section is not inside mj-wrapper, we need to remove the conditional `if mso <table>` and `if mso <tr><td>`
        $innerHtml = preg_replace('/^<!--\[if mso \| IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0">/', '<!--[if mso | IE]>', $innerHtml);
        $innerHtml = preg_replace('/<\/table><!\[endif]-->$/', '<![endif]-->', $innerHtml);
        $innerHtml = str_replace('<!--[if mso | IE]><![endif]-->', '', $innerHtml);
        if (!$isWrapped) {
            $innerHtml = preg_replace('/(<!--\[if mso \| IE]>)<tr><td[^>]*>/', '$1', $innerHtml, 1); // replace first
            $innerHtml = preg_replace("/(.*)<\/td><\/tr><!\[endif]-->/", '$1<![endif]-->', $innerHtml); // replace last
        }

        //Each converted template has its own style rules, so we need to extract them as well
        $htmlHead = $this->extractHtml($convertedTemplate, 'head');
        if (preg_match('#(<style.*</style>)#s', $htmlHead, $matches)) {
            $templateStyles = trim($matches[1])."\n";
        } else {
            $templateStyles = '';
        }
        return [
            'content' => $innerHtml,
            'styles' => $templateStyles,
        ];
    }

    /**
     * @param string $templateContent
     * @param string $containerSelector
     * @param string $mailType
     *
     * @return string
     */
    private function replaceContainerWithTwigCondition($templateContent, $containerSelector, $mailType)
    {
        $crawler = new Crawler($templateContent);

        $crawler->filter($containerSelector)->each(function (Crawler $crawler) use ($mailType) {
            foreach ($crawler as $node) {
                $replaceNodes = [];
                $replaceNodes[] = $node->ownerDocument->createTextNode('{% if templateType == \'' . $mailType . '\' %}'.PHP_EOL);
                /** @var DOMElement $childNode */
                foreach ($node->childNodes as $childNode) {
                    $replaceNodes[] = $childNode->cloneNode(true);
                }
                $replaceNodes[] = $node->ownerDocument->createTextNode(PHP_EOL . '{% endif %}');

                foreach ($replaceNodes as $childNode) {
                    $node->parentNode->insertBefore($childNode, $node);
                }
                $node->parentNode->removeChild($node);
            }
        });

        $filteredContent = '';
        foreach ($crawler as $domElement) {
            $filteredContent .= $domElement->ownerDocument->saveHTML($domElement);
        }

        // Since DOMDocument::saveHTML converts special characters into special HTML characters we revert them back
        $filteredContent = htmlspecialchars_decode($filteredContent);

        return $filteredContent;
    }

    /**
     * @param string $htmlContent
     * @param string $selector
     *
     * @return string
     */
    private function extractHtml($htmlContent, $selector, $nodeIndex = null)
    {
        $htmlContent = $this->replaceContainerWithTwigCondition($htmlContent, 'html-only', 'html');
        $htmlContent = $this->replaceContainerWithTwigCondition($htmlContent, 'txt-only', 'txt');

        //MJML returns a full html template, get only the body content
        $crawler = new Crawler($htmlContent);
        /** @var Crawler $filteredCrawler */
        $filteredCrawler = $crawler->filter($selector);
        if (null === $nodeIndex) {
            $nodeList = $filteredCrawler;
        } else {
            $nodeList = $filteredCrawler->getNode($nodeIndex)->childNodes;
        }
        $extractedHtml = '';
        /** @var DOMElement $childNode */
        foreach ($nodeList as $childNode) {
            $extractedHtml .= $childNode->ownerDocument->saveHTML($childNode);
        }

        // Since DOMDocument::saveHTML converts special characters into special HTML characters we revert them back
        $extractedHtml = htmlspecialchars_decode($extractedHtml);

        return $extractedHtml;
    }

    /**
     * @param string $templateContent
     *
     * @return string|null
     * @throws \Twig\Error\Error
     */
    private function convertMjml($templateContent)
    {
        //Print the conversion layout in a file and renders it (Twig needs a file as input)
        //Use content md5 as output name to avoid twig caching templates with same name (can happen with modules)
        $conversionTemplatePath = $this->tempDir.'/'.md5($templateContent);
        if (!is_dir($this->tempDir)) {
            $this->fileSystem->mkdir($this->tempDir);
        }

        //Transform {{ }} statements so that they are not executed
        $templateContent = preg_replace('#{{ (.*?) }}#', '%% \1 %%', $templateContent);

        file_put_contents($conversionTemplatePath, $templateContent);

        $renderedLayout = $this->engine->render($conversionTemplatePath);
        //Transform back {{ }} statements
        $renderedLayout = preg_replace('#%% (.*?) %%#', '{{ \1 }}', $renderedLayout);

        file_put_contents($conversionTemplatePath.'.mjml', $renderedLayout);

        //Convert the conversion template (MJML code is compiled and the template contains mj-raw tags to include the twig tags)
        $convertedTemplate = $this->mjmlConverter->convert($renderedLayout);

        return $convertedTemplate;
    }

    /**
     * @return string
     */
    private function getParentLayout()
    {
        preg_match('/{% extends (.*?) %}/', $this->templateContent, $matches);
        if (count($matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @param string $layout
     *
     * @return string
     */
    private function getLayoutTheme($layoutPath)
    {
        preg_match('#@MjmlMailThemes/(.*?)/#', $layoutPath, $matches);
        if (count($matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @return string
     */
    private function getLayoutTitle()
    {
        preg_match('/{% block title %}(.*){% endblock %}/', $this->templateContent, $matches);
        if (count($matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @param string $layoutPath
     * @param string $newTheme
     *
     * @return string|string[]|null
     */
    private function convertTwigLayoutPath($layoutPath, $newTheme)
    {
        $mjmlTheme = $this->getLayoutTheme($layoutPath);
        $twigLayoutPath = preg_replace('#@MjmlMailThemes/'.$mjmlTheme.'/#', '@MailThemes/'.$newTheme.'/', $layoutPath);
        $twigLayoutPath = preg_replace('#mjml.twig#', 'html.twig', $twigLayoutPath);

        return $twigLayoutPath;
    }
}
