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

namespace AppBundle\Mjml;

use AppBundle\Exception\FileNotFoundException;
use AppBundle\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class MjmlConverter
{
    /** @var bool */
    private $binaryMode;

    /** @var string */
    private $applicationId;

    /** @var string */
    private $secretKey;

    /** @var string */
    private $tempDir;

    /** @var Filesystem */
    private $fileSystem;

    /** @var bool */
    private $useNpm;

    /**
     * @param bool $useNpm
     * @param string $applicationId
     * @param string $secretKey
     * @param bool $binaryMode
     * @param string $tempDir
     * @throws InvalidArgumentException
     */
    public function __construct(
        $applicationId = ',',
        $secretKey = '',
        $binaryMode = true,
        $tempDir = '',
        $useNpm = false
    ) {
        if (!$binaryMode && (empty($applicationId) || empty($secretKey))) {
            throw new InvalidArgumentException('You need to enable at least one of binary or api mode');
        }

        $this->binaryMode = $binaryMode;
        $this->applicationId = $applicationId;
        $this->secretKey = $secretKey;
        $this->tempDir = empty($tempDir) ? sys_get_temp_dir().'/mjml' : $tempDir;
        $this->fileSystem = new Filesystem();
        $this->useNpm = $useNpm;
    }

    public function convert($mjmlContent)
    {
        $lastException = null;
        $convertedHtml = null;
        if ($this->binaryMode) {
            try {
                $convertedHtml = $this->convertWithBinary($mjmlContent);
            } catch (\Exception $e) {
                $lastException = $e;
            }
        }

        if (null === $convertedHtml && !empty($this->applicationId) && !empty($this->secretKey)) {
            //todo: implement API convertion
        }

        if (null === $convertedHtml && null !== $lastException) {
            throw $lastException;
        }

        return $convertedHtml;
    }

    /**
     * @param string $mjmlContent
     *
     * @return string
     * @throws FileNotFoundException
     * @throws MjmlException
     */
    private function convertWithBinary($mjmlContent)
    {
        $binPath = $this->getMjmlBinaryPath();
        if (empty($binPath)) {
            throw new FileNotFoundException('Could not find mjml binary on your system');
        }

        if (!is_dir($this->tempDir)) {
            $this->fileSystem->mkdir($this->tempDir);
        }
        $tmpFilePath = $this->tempDir.'/'.uniqid().'.mjml';
        file_put_contents($tmpFilePath, $mjmlContent);

        $convertCmd = $binPath.' '.$tmpFilePath;
        $convertProcess = new Process($convertCmd);
        $convertProcess->run();
        if (!$convertProcess->isSuccessful()) {
            throw new MjmlException('Could not convert mjml file', $convertProcess->getErrorOutput());
        }

        //Remove file annotation
        $mjmlOutput = $convertProcess->getOutput();
        $mjmlOutput = preg_replace('/<!-- FILE: .*\.mjml -->/', '', $mjmlOutput);

        return trim($mjmlOutput)."\n";
    }

    /**
     * @return string
     */
    private function getMjmlBinaryPath(): string
    {
        if ($this->useNpm) {
            return 'npx mjml';
        }
        $process = new Process('which mjml');
        $process->run();
        if (!$process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }
}
