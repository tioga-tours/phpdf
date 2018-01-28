<?php
namespace PhPdf;


class Shell
{
    /**
     * Test if a command exists in the shell. Works on Windows, Linux, Unix
     *
     * @param string $command
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