<?php
declare(strict_types = 1);
namespace Browscap\Command;

use Browscap\Helper\LoggerHelper;
use Ergebnis\Json\Normalizer;
use Ergebnis\Json\Printer\Printer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class RewriteTestFixturesCommand extends Command
{
    protected function configure() : void
    {
        $this
            ->setName('rewrite-test-fixtures')
            ->setDescription('rewrites the test files in the fixtures folder');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output) : ?int
    {
        $loggerHelper = new LoggerHelper();
        $logger       = $loggerHelper->create($output);

        $resourcePath = __DIR__ . '/../../../tests/fixtures';

        $normalizer = new Normalizer\FinalNewLineNormalizer();
        $format     = new Normalizer\Format\Format(
            Normalizer\Format\JsonEncodeOptions::fromInt(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            Normalizer\Format\Indent::fromSizeAndStyle(2, 'space'),
            Normalizer\Format\NewLine::fromString("\n"),
            true
        );

        $printer   = new Printer();
        $formatter = new Normalizer\Format\Formatter($printer);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.json');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($resourcePath);

        foreach ($finder as $file) {
            $logger->info('read source file ' . $file->getPathname());

            try {
                $json = $file->getContents();
            } catch (\RuntimeException $e) {
                $logger->critical(new \Exception(sprintf('could not read file "%s"', $file->getPathname()), 0, $e));

                continue;
            }

            try {
                $normalized = (new Normalizer\FixedFormatNormalizer($normalizer, $format, $formatter))->normalize(Normalizer\Json::fromEncoded($json));
            } catch (\Throwable $e) {
                $logger->critical(new \Exception(sprintf('file "%s" is not valid', $file->getPathname()), 0, $e));

                continue;
            }

            file_put_contents($file->getPathname(), $normalized);
        }

        $output->writeln('Done');

        return 0;
    }
}
