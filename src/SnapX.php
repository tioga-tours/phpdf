<?php

namespace SnapX;

use Knp\Snappy\Pdf;

class SnapX
{
    protected static $pdfBinary;
    
    /**
     * Get a new Snappy PDF creator
     *
     * @param array $options
     * @param array|null $env
     *
     * @return \Knp\Snappy\Pdf
     * @throws \Exception
     */
    public static function createPdf(array $options = array(), array $env = null): \Knp\Snappy\Pdf
    {
        return new Pdf(self::getPdfBinary(), $options, $env);
    }
    
    public static function getPdfBinary()
    {
        if (self::$pdfBinary !== null) {
            return $pdfBinary;
        }
        
        // Detect vendor directory, usually three
        if (file_exists(__DIR__ . '/../../../autoload.php') === true) {
            $vendorPath = __DIR__ . '/../../../';
        } else {
            // We are probably in test mode and have our own vendor dir
            $vendorPath = __DIR__ . '/../';
        }
        
        if (self::commandExists('wkhtmltopdf')) {
            $osVersion = php_uname('v');
            if (stristr($osVersion, 'ubuntu') !== false || stristr($osVersion, 'debian') === false) {
                // wkhtmltopdf cannot run headless on debian/ubuntu, work around this
                self::$pdfBinary = __DIR__ . '/wkhtmltopdf.sh';
                
                if (false === self::commandExists('xvfb-run')) {
                    throw new \Exception('To use the OS wkhtmltopdf version, you must install xvfb on Debian an Ubuntu');
                }
                
                if (false === is_executable(self::$pdfBinary)) {
                    throw new \Exception('The binary is not executable at: ' . self::$pdfBinary);
                }
                
            } else {
                self::$pdfBinary = 'wkhtmltopdf';
            }
            
            return self::$pdfBinary;
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
        
        self::$pdfBinary = $binary;
        return self::$pdfBinary;
    }
    
    /**
     *
     * @param string $cmd
     *
     * @return boolean
     */
    public static function commandExists($command): bool
    {
        $command = trim($command, '"');
        
        if (file_exists($command) === true) {
            return true;
        }
        
        $whereIsCommand = (PHP_OS === 'WINNT') ? 'where' : 'which';
        
        $pipes = [];
        $process = proc_open(
            "$whereIsCommand $command",
            [
                0 => ["pipe", "r"], //STDIN
                1 => ["pipe", "w"], //STDOUT
                2 => ["pipe", "w"], //STDERR
            ],
            $pipes
        );
        if ($process !== false) {
            $stdout = stream_get_contents($pipes[1]);
            //$stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            
            return $stdout !== '';
        }
        
        return false;
    }
}