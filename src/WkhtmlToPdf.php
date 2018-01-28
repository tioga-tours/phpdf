<?php

namespace PhPdf;


use Symfony\Component\Process\Process;

class WkhtmlToPdf
{
    protected static $binary;

    protected static $tempDir = null;

    protected $options = [
        'margin-top' => '10mm',
        'margin-bottom' => '10mm',
        'margin-left' => '10mm',
        'margin-right' => '10mm',
    ];

    /**
     * If false, then no TOC is generated
     *
     * @var bool|array
     */
    protected $tocOptions = false;

    protected $cleanupFiles = [];

    /**
     * @var \Symfony\Component\Process\Process
     */
    protected $process = null;

    /**
     * @var array
     */
    protected $contents = [];

    /**
     * @var string
     */
    protected $outputFile = null;

    protected static $tocXsl = null;

    /**
     * The basic header/footer html. Will be used to place custom html in. Headers and footers are quite
     * difficult to get right with wkhtmltopdf, this should solve some problems
     *
     * @var string
     */
    public static $headerFooterHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf8"/>
    <title></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    html { margin:0; padding:0 0 4px 0; width:100%; height:{{height}}; overflow: hidden; }
    body.pdf { margin:0; padding:0; width: 100%; height: 100%; } 
    </style>
</head>
<body class="pdf">
{{content}}
</body>
</html>
HTML;

    const ACCEPTED_OPTIONS = [
        // Global Options:
        'collate' => ['description' => 'Collate when printing multiple copies (default)'],
        'no-collate' => ['description' => 'Do not collate when printing multiple copies'],
        'cookie-jar' => ['args' => ['<path>'], 'description' => ' Read and write cookies from and to the supplied cookie jar file'],
        'copies' => ['args' => ['<number>'], 'description' => ' Number of copies to print into the pdf file ', 'default' => '1'],
        'dpi' => ['args' => ['<dpi>'], 'description' => ' Change the dpi explicitly (this has no effect on X11 based systems) ', 'default' => '96'],
        'extended-help' => ['description' => 'Display more extensive help, detailing less common command switches'],
        'grayscale' => ['description' => 'PDF will be generated in grayscale'],
        'help' => ['description' => 'Display help'],
        'htmldoc' => ['description' => 'Output program html help'],
        'image-dpi' => ['args' => ['<integer>'], 'description' => ' When embedding images scale them down to this dpi ', 'default' => '600'],
        'image-quality' => ['args' => ['<integer>'], 'description' => ' When jpeg compressing images use this quality ', 'default' => '94'],
        'license' => ['description' => 'Output license information and exit'],
        'lowquality' => ['description' => 'Generates lower quality pdf/ps. Useful to shrink the result document space'],
        'manpage' => ['description' => 'Output program man page'],
        'margin-bottom' => ['args' => ['<unitreal>'], 'description' => ' Set the page bottom margin'],
        'margin-left' => ['args' => ['<unitreal>'], 'description' => ' Set the page left margin ', 'default' => '10mm'],
        'margin-right' => ['args' => ['<unitreal>'], 'description' => ' Set the page right margin ', 'default' => '10mm'],
        'margin-top' => ['args' => ['<unitreal>'], 'description' => ' Set the page top margin'],
        'orientation' => ['args' => ['<orientation>'], 'description' => ' Set orientation to Landscape or Portrait ', 'default' => 'Portrait'],
        'page-height' => ['args' => ['<unitreal>'], 'description' => ' Page height'],
        'page-size' => ['args' => ['<Size>'], 'description' => ' Set paper size to: A4, Letter, etc. ', 'default' => 'A4'],
        'page-width' => ['args' => ['<unitreal>'], 'description' => ' Page width'],
        'no-pdf-compression' => ['description' => 'Do not use lossless compression on pdf objects'],
        'quiet' => ['description' => 'Be less verbose'],
        'read-args-from-stdin' => ['description' => 'Read command line arguments from stdin'],
        'readme' => ['description' => 'Output program readme'],
        'title' => ['args' => ['<text>'], 'description' => ' The title of the generated pdf file (The title of the first document is used if not specified)'],
        'use-xserver' => ['description' => 'Use the X server (some plugins and other stuff might not work without X11)'],
        'version' => ['description' => 'Output version information and exit'],

        // Outline Options:
        'dump-default-toc-xsl' => ['description' => 'Dump the default TOC xsl style sheet to stdout'],
        'dump-outline' => ['args' => ['<file>'], 'description' => ' Dump the outline to a file'],
        'outline' => ['description' => 'Put an outline into the pdf (default)'],
        'no-outline' => ['description' => 'Do not put an outline into the pdf'],
        'outline-depth' => ['args' => ['<level>'], 'description' => ' Set the depth of the outline ', 'default' => '4'],

        //Page Options:
        'allow' => ['args' => ['<path>'], 'description' => ' Allow the file or files from the specified folder to be loaded', 'repeatable' => true],
        'background' => ['description' => 'Do print background (default)'],
        'no-background' => ['description' => 'Do not print background'],
        'bypass-proxy-for' => ['args' => ['<value>'], 'description' => ' Bypass proxy for host', 'repeatable' => true],
        'cache-dir' => ['args' => ['<path>'], 'description' => ' Web cache directory'],
        'checkbox-checked-svg' => ['args' => ['<path>'], 'description' => ' Use this SVG file when rendering checked checkboxes'],
        'checkbox-svg' => ['args' => ['<path>'], 'description' => ' Use this SVG file when rendering unchecked checkboxes'],
        'cookie' => ['args' => ['<name>', '<value>'], 'description' => ' Set an additional cookie (repeatable), value should be url encoded.'],
        'custom-header' => ['args' => ['<name>', '<value>'], 'description' => ' Set an additional HTTP header', 'repeatable' => true],
        'custom-header-propagation' => ['description' => 'Add HTTP headers specified by --custom-header for each resource request.'],
        'no-custom-header-propagation' => ['description' => 'Do not add HTTP headers specified by --custom-header for each resource request.'],
        'debug-javascript' => ['description' => 'Show javascript debugging output'],
        'no-debug-javascript' => ['description' => 'Do not show javascript debugging output (default)'],
        'default-header' => ['description' => 'Add a default header, with the name of the page to the left, and the page number to the right, this is short for: --header-left=\'[webpage]\' --header-right=\'[page]/[toPage]\' --top 2cm --header-line'],
        'encoding' => ['args' => ['<encoding>'], 'description' => ' Set the default text encoding, for input'],
        'disable-external-links' => ['description' => 'Do not make links to remote web pages'],
        'enable-external-links' => ['description' => 'Make links to remote web pages (default)'],
        'disable-forms' => ['description' => 'Do not turn HTML form fields into pdf form fields (default)'],
        'enable-forms' => ['description' => 'Turn HTML form fields into pdf form fields'],
        'images' => ['description' => 'Do load or print images (default)'],
        'no-images' => ['description' => 'Do not load or print images'],
        'disable-internal-links' => ['description' => 'Do not make local links'],
        'enable-internal-links' => ['description' => 'Make local links (default)'],
        'disable-javascript' => ['description' => 'Do not allow web pages to run javascript'],
        'enable-javascript' => ['description' => 'Do allow web pages to run javascript (default)'],
        'javascript-delay' => ['args' => ['<msec>'], 'description' => ' Wait some milliseconds for javascript finish ', 'default' => '200'],
        'keep-relative-links' => ['description' => 'Keep relative external links as relative external links'],
        'load-error-handling' => ['args' => ['<handler>'], 'description' => ' Specify how to handle pages that fail to load: abort, ignore or skip ', 'default' => 'abort'],
        'load-media-error-handling' => ['args' => ['<handler>'], 'description' => ' Specify how to handle media files that fail to load: abort, ignore or skip ', 'default' => 'ignore'],
        'disable-local-file-access' => ['description' => 'Do not allowed conversion of a local file to read in other local files, unless explicitly allowed with --allow'],
        'enable-local-file-access' => ['description' => 'Allowed conversion of a local file to read in other local files. (default)'],
        'minimum-font-size' => ['args' => ['<int>'], 'description' => ' Minimum font size'],
        'exclude-from-outline' => ['description' => 'Do not include the page in the table of contents and outlines'],
        'include-in-outline' => ['description' => 'Include the page in the table of contents and outlines (default)'],
        'page-offset' => ['args' => ['<offset>'], 'description' => ' Set the starting page number ', 'default' => '0'],
        'password' => ['args' => ['<password>'], 'description' => ' HTTP Authentication password'],
        'disable-plugins' => ['description' => 'Disable installed plugins (default)'],
        'enable-plugins' => ['description' => 'Enable installed plugins (plugins will likely not work)'],
        'post' => ['args' => ['<name>', '<value>'], 'description' => ' Add an additional post field', 'repeatable' => true],
        'post-file' => ['args' => ['<name>', '<path>'], 'description' => ' Post an additional file', 'repeatable' => true],
        'print-media-type' => ['description' => 'Use print media-type instead of screen'],
        'no-print-media-type' => ['description' => 'Do not use print media-type instead of screen (default)'],
        'proxy' => ['args' => ['<proxy>'], 'description' => ' Use a proxy'],
        'radiobutton-checked-svg' => ['args' => ['<path>'], 'description' => ' Use this SVG file when rendering checked radiobuttons'],
        'radiobutton-svg' => ['args' => ['<path>'], 'description' => ' Use this SVG file when rendering unchecked radiobuttons'],
        'resolve-relative-links' => ['description' => 'Resolve relative external links into absolute links (default)'],
        'run-script' => ['args' => ['<js>'], 'description' => ' Run this additional javascript after the page is done loading', 'repeatable' => true],
        'disable-smart-shrinking' => ['description' => 'Disable the intelligent shrinking strategy used by WebKit that makes the pixel/dpi ratio none constant'],
        'enable-smart-shrinking' => ['description' => 'Enable the intelligent shrinking strategy used by WebKit that makes the pixel/dpi ratio none constant (default)'],
        'stop-slow-scripts' => ['description' => 'Stop slow running javascripts (default)'],
        'no-stop-slow-scripts' => ['description' => 'Do not Stop slow running javascripts'],
        'disable-toc-back-links' => ['description' => 'Do not link from section header to toc (default)'],
        'enable-toc-back-links' => ['description' => 'Link from section header to toc'],
        'user-style-sheet' => ['args' => ['<url>'], 'description' => ' Specify a user style sheet, to load with every page'],
        'username' => ['args' => ['<username>'], 'description' => ' HTTP Authentication username'],
        'viewport-size' => ['args' => ['<>'], 'description' => ' Set viewport size if you have custom scrollbars or css attribute overflow to emulate window size'],
        'window-status' => ['args' => ['<windowStatus>'], 'description' => ' Wait until window.status is equal to this string before rendering page'],
        'zoom' => ['args' => ['<float>'], 'description' => ' Use this zoom factor ', 'default' => '1'],

        //Headers And Footer Options:
        'footer-center' => ['args' => ['<text>'], 'description' => ' Centered footer text'],
        'footer-font-name' => ['args' => ['<name>'], 'description' => ' Set footer font name ', 'default' => 'Arial'],
        'footer-font-size' => ['args' => ['<size>'], 'description' => ' Set footer font size ', 'default' => '12'],
        'footer-html' => ['args' => ['<url>'], 'description' => ' Adds a html footer'],
        'footer-left' => ['args' => ['<text>'], 'description' => ' Left aligned footer text'],
        'footer-line' => ['description' => 'Display line above the footer'],
        'no-footer-line' => ['description' => 'Do not display line above the footer (default)'],
        'footer-right' => ['args' => ['<text>'], 'description' => ' Right aligned footer text'],
        'footer-spacing' => ['args' => ['<real>'], 'description' => ' Spacing between footer and content in mm ', 'default' => '0'],
        'header-center' => ['args' => ['<text>'], 'description' => ' Centered header text'],
        'header-font-name' => ['args' => ['<name>'], 'description' => ' Set header font name ', 'default' => 'Arial'],
        'header-font-size' => ['args' => ['<size>'], 'description' => ' Set header font size ', 'default' => '12'],
        'header-html' => ['args' => ['<url>'], 'description' => ' Adds a html header'],
        'header-left' => ['args' => ['<text>'], 'description' => ' Left aligned header text'],
        'header-line' => ['description' => 'Display line below the header'],
        'no-header-line' => ['description' => 'Do not display line below the header (default)'],
        'header-right' => ['args' => ['<text>'], 'description' => ' Right aligned header text'],
        'header-spacing' => ['args' => ['<real>'], 'description' => ' Spacing between header and content in mm ', 'default' => '0'],
        'replace' => ['args' => ['<name>', '<value>'], 'description' => ' Replace [name] with value in header and footer', 'repeatable' => true],
    ];

    const ACCEPTED_TOC_OPTIONS = [
        //TOC Options:
        'disable-dotted-lines' => ['description' => 'Do not use dotted lines in the toc'],
        'toc-header-text' => ['args' => ['<text>'], 'description' => ' The header text of the toc ', 'default' => 'Table of Contents'],
        'toc-level-indentation' => ['args' => ['< width>'], 'description' => ' For each level of headings in the toc indent by this length ', 'default' => '1em'],
        'disable-toc-links' => ['description' => 'Do not link from toc to sections'],
        'toc-text-size-shrink' => ['args' => ['<real>'], 'description' => ' For each level of headings in the toc the font is scaled by this factor ', 'default' => '0.8'],
        'xsl-style-sheet' => ['args' => ['<file>'], 'description' => ' Use the supplied xsl style sheet for printing the table of content'],
    ];

    /**
     * WkhtmlToPdf constructor.
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @param string|string[] $html
     * @return WkhtmlToPdf
     */
    public function addHtml($html): WkhtmlToPdf
    {
        if (false === is_array($html)) {
            $html = [$html];
        }

        $files = [];
        foreach ($html as $h) {
            $file = tempnam(self::getTempDir(), 'phppdf_') . '.html';
            file_put_contents($file, $h);
            $files[] = $file;
            $this->cleanupFiles[] = $file;
        }

        $this->contents = array_merge($this->contents, $files);
        return $this;
    }

    /**
     * @param string|string[] $urls
     * @return $this
     */
    public function addUrl($urls)
    {
        if (false === is_array($urls)) {
            $urls = [$urls];
        }

        $this->contents = array_merge($this->contents, $urls);

        return $this;
    }

    /**
     * @param null|string $outputFile
     * @param bool $async
     * @return false|WkhtmlToPdf|string
     * @throws \Exception
     */
    public function generate(?string $outputFile = null, $async = false)
    {
        if ($this->process !== null && $this->process->isRunning()) {
            throw new \Exception('There is a PDF generation in process');
        }

        if ($outputFile === null) {
            $outputFile = tempnam(self::getTempDir(), 'phppdf_pdf_');
            $this->cleanupFiles[] = $outputFile;
        }
        $this->outputFile = $outputFile;

        $this->executeCommand();

        if ($async === false) {
            return $this->wait();
        }
        return $this;
    }

    /**
     * @return false|string
     */
    public function wait()
    {
        $this->process->wait();

        if (false === $this->process->isSuccessful()) {
            $this->cleanupFiles[] = $this->outputFile;
            return false;
        }
        return $this->outputFile;
    }

    public function getErrorOutput()
    {
        return $this->process->getErrorOutput();
    }

    /**
     * @return null
     * @throws \Exception
     */
    public function getDefaultTocXsl(): string
    {
        if (self::$tocXsl !== null) {
            return self::$tocXsl;
        }

        $pdfGenerator = new self(['dump-default-toc-xsl' => true]);
        $pdfGenerator->addHtml('<html><head></head><body><h1>Head</h1><h2>Head 2</h2><h3>Head 4</h3><h4>Head 4</h4><h5>Head 5</h5><h6>Head 6</h6></body></html>');
        $pdfGenerator->generate();

        self::$tocXsl = $pdfGenerator->process->getOutput();

        return self::$tocXsl;
    }

    protected function executeCommand()
    {
        $cmd = [self::getBinary()];

        foreach ($this->options as $option => $values) {
            if (false === is_array($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                if ($value === false) {
                    continue;
                }
                $cmd[] = '--' . $option;

                $value = self::processOptionValue($option, $value, $this->options);
                if ($value !== null && $value !== true) {
                    $cmd[] = $value;
                }
            }
        }

        if ($this->tocOptions !== false) {
            $cmd[] = 'toc';
            foreach ($this->tocOptions as $option => $values) {
                if (false === is_array($values)) {
                    $values = [$values];
                }
                foreach ($values as $value) {
                    if ($value === false) {
                        continue;
                    }
                    $cmd[] = '--' . $option;
    
                    $value = self::processOptionValue($option, $value, $this->options, 'toc');
                    if ($value !== null && $value !== true) {
                        $cmd[] = $value;
                    }
                }
            }
        }

        $cmd = array_merge($cmd, $this->contents, [$this->outputFile]);

        $this->process = new Process($cmd);
        $this->process->start();
    }

    public function __destruct()
    {
        $this->cleanUp();
    }

    protected function cleanUp()
    {
        foreach ($this->cleanupFiles as $deleteFile) {
            @unlink($deleteFile);
        }
        $this->cleanupFiles = [];
    }

    public function isRunning()
    {
        return $this->process->isRunning();
    }

    /**
     * Set options to wkhtmltopdf
     *
     * @see https://wkhtmltopdf.org/usage/wkhtmltopdf.txt
     *
     * @param array $options
     * @return WkhtmlToPdf
     * @throws \Exception
     */
    public function setOptions(array $options): WkhtmlToPdf
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
        return $this;
    }

    /**
     * @param string $option
     * @param string $value
     * @return WkhtmlToPdf
     * @throws \Exception
     */
    public function setOption(string $option, $value): WkhtmlToPdf
    {
        self::validateOption($option, $value);
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * Set options to wkhtmltopdf
     *
     * @see https://wkhtmltopdf.org/usage/wkhtmltopdf.txt
     *
     * @param array $options
     * @return WkhtmlToPdf
     * @throws \Exception
     */
    public function setTocOptions(array $options): WkhtmlToPdf
    {
        foreach ($options as $option => $value) {
            $this->setTocOption($option, $value);
        }
        return $this;
    }

    /**
     * @param string $option
     * @param string $value
     * @return WkhtmlToPdf
     * @throws \Exception
     */
    public function setTocOption(string $option, string $value): WkhtmlToPdf
    {
        self::validateOption($option, $value, 'toc');
        $this->enableToc();
        $this->tocOptions[$option] = $value;
        return $this;
    }

    public function enableToc($flag = true)
    {
        if ($flag === true && $this->tocOptions === false) {
            $this->tocOptions = [];
        } elseif ($flag === false) {
            $this->tocOptions = false;
        }
    }

    protected static function processOptionValue(string $option, $value, array $allOptions, ?string $context = null)
    {
        switch ($option) {
            case 'header-html':
            case 'footer-html':
                if (false === file_exists($value)) {
                    if (false === stristr($value, '<body')) {
                        $value = str_replace(
                            ['{{content}}', '{{height}}'],
                            [$value, $option === 'header-html' ? $allOptions['margin-top'] : $allOptions['margin-bottom'] ],
                            self::$headerFooterHtml
                        );

                    }
                    $html = $value;
                    $value = tempnam(self::getTempDir(), 'pdfheader') . '.html';
                    file_put_contents($value, $html);
                }
                break;
            case 'xsl-style-sheet':
                if (false === file_exists($value)) {
                    $xsl = $value;
                    $value = tempnam(self::getTempDir(), 'xsl') . '.xsl';
                    file_put_contents($value, $xsl);
                }
                break;
        }
        return $value;
    }

    /**
     * @param string $option
     * @param string $value
     * @param null|string $context
     * @throws \Exception
     */
    protected function validateOption(string $option, string $value, ?string $context = null)
    {
        switch ($context) {
            case null:
                if (false === isset(self::ACCEPTED_OPTIONS[$option])) {
                    throw new \Exception('Invalid option: ' . $option);
                }
                break;
            case 'toc':
                if (false === isset(self::ACCEPTED_TOC_OPTIONS[$option])) {
                    throw new \Exception('Invalid TOC option: ' . $option);
                }
                break;
            default:
                throw new \Exception('Invalid context: ' . $context);
        }
    }

    public static function getTempDir(): string
    {
        if (self::$tempDir === null) {
            self::$tempDir = sys_get_temp_dir();
        }
        return self::$tempDir;
    }

    public static function setTempDir(string $tempDir)
    {
        self::$tempDir = $tempDir;
    }

    /**
     * Set a custom binary
     *
     * @param string $binary
     */
    public static function setBinary(string $binary)
    {
        self::$binary = $binary;
    }

    /**
     * Detect the correct binary, if already set or detected, it will return this binary
     * @return string
     * @throws \Exception
     */
    public static function getBinary()
    {
        if (self::$binary !== null) {
            return self::$binary;
        }

        // Detect vendor directory, usually three
        if (file_exists(__DIR__ . '/../../../autoload.php') === true) {
            $vendorPath = __DIR__ . '/../../../';
        } else {
            // We are probably in test mode and have our own vendor dir
            $vendorPath = __DIR__ . '/../vendor/';
        }

        if (Shell::commandExists('wkhtmltopdf')) {
            $osVersion = php_uname('v');
            if (stristr($osVersion, 'ubuntu') !== false || stristr($osVersion, 'debian') === false) {
                // wkhtmltopdf cannot run headless on debian/ubuntu, work around this
                self::$binary = __DIR__ . '/wkhtmltopdf.sh';

                if (false === Shell::commandExists('xvfb-run')) {
                    throw new \Exception('To use the OS wkhtmltopdf version, you must install xvfb on Debian an Ubuntu');
                }

                if (false === is_executable(self::$binary)) {
                    throw new \Exception('The binary is not executable at: ' . self::$binary);
                }

            } else {
                self::$binary = 'wkhtmltopdf';
            }

            return self::$binary;
        } elseif (PHP_OS === 'WINNT') {
            // We are on Windows, use wemersonjanuario's package
            $binary = $vendorPath . 'wemersonjanuario/wkhtmltopdf-windows/bin/';
            $binary .= strstr(php_uname('m'), '64') !== false ? '64bit' : '32bit';
            $binary .= '/wkhtmltopdf.exe';
        } else {
            // We are on Linux/Unix/Mac, use h4cc's package
            $suffix = strstr(php_uname('m'), '64') !== false ? 'amd64' : 'i386';
            $binary = $vendorPath . 'h4cc/wkhtmltopdf-' . $suffix;
            $binary .= '/bin/wkhtmltopdf' . $suffix;
        }

        if (false === file_exists($binary)) {
            throw new \Exception('Could not find binary: ' . $binary);
        }

        self::$binary = $binary;
        return self::$binary;
    }
}