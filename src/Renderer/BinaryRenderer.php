<?php

namespace NotFloran\MjmlBundle\Renderer;

use Symfony\Component\Process\Process;

final class BinaryRenderer implements RendererInterface
{
    private const VERSION_4 = 4;
    private const VERSION_BEFORE_4 = 3;

    /**
     * @var string
     */
    private $bin;

    /**
     * @var bool
     */
    private $minify;

    /**
     * @var int|null
     */
    private $mjmlVersion;

    public function __construct(string $bin, bool $minify)
    {
        $this->bin = $bin;
        $this->minify = $minify;
    }

    private function getMjmlVersion() : int
    {
        if ($this->mjmlVersion === null) {
            $process = new Process([
                $this->bin,
                '-V',
            ]);
            $process->run();

            if (true !== $process->isSuccessful()) {
                throw new \InvalidArgumentException(sprintf(
                    "Couldn't find the MJML binary"
                ));
            }

            $this->mjmlVersion = self::VERSION_4;
            if (strpos($process->getOutput(), 'mjml-core: 4.') === false) {
                $this->mjmlVersion = self::VERSION_BEFORE_4;
            }
        }

        return $this->mjmlVersion;
    }

    public function render(string $mjmlContent) : string
    {
        $version = $this->getMjmlVersion();

        // Tab arguments
        $arguments = [
            $this->bin,
            '-i',
            '-s',
        ];

        $strictArgument = '-l';
        if ($version === self::VERSION_4) {
            $strictArgument = '--config.validationLevel';
        }

        array_push($arguments, $strictArgument, 'strict');

        if (true === $this->minify) {
            if ($version === self::VERSION_4) {
                array_push($arguments, '--config.minify', 'true');
            } else {
                $arguments[] = '-m';
            }
        }

        // Create process
        $process = new Process($arguments);
        $process->setInput($mjmlContent);
        $process->run();

        // Executes after the command finishes
        if (true !== $process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'The exit status code \'%s\' says something went wrong:' . "\n"
                . 'stderr: "%s"' . "\n"
                . 'stdout: "%s"' . "\n"
                . 'command: %s.',
                $process->getStatus(),
                $process->getErrorOutput(),
                $process->getOutput(),
                $process->getCommandLine()
            ));
        }

        return $process->getOutput();
    }
}
