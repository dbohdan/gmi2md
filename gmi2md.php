#! /usr/bin/env php
<?php

declare(strict_types=1);

interface LineTypeInterface
{
    public static function isType(string $line): bool;
    public static function convert(string $line): string;
}

final class BlankLine implements LineTypeInterface
{
    public static function isType(string $line): bool
    {
        return $line === '';
    }

    public static function convert(string $line): string
    {
        return '';
    }
}

final class BlockquoteLine implements LineTypeInterface
{
    private const PREFIX = '>';

    public static function isType(string $line): bool
    {
        return str_starts_with($line, self::PREFIX);
    }

    public static function convert(string $line): string
    {
        return self::PREFIX.htmlspecialchars(substr($line, strlen(self::PREFIX)));
    }
}

final class HeadingLine implements LineTypeInterface
{
    private const REGEX = '/^(#{1,3})[ \t]*/';

    public static function isType(string $line): bool
    {
        return (bool) preg_match(self::REGEX, $line);
    }

    public static function convert(string $line): string
    {
        return preg_replace(self::REGEX, '\1 ', $line);
    }
}

final class LinkLine implements LineTypeInterface
{
    private const PREFIX = '=>';
    private const HTTP = 'http';
    private const WHITESPACE_REGEX = '/[ \t]+/';

    public static function isType(string $line): bool
    {
        return str_starts_with($line, self::PREFIX);
    }

    public static function convert(string $line): string
    {
        $link = trim(substr($line, strlen(self::PREFIX)));

        return str_starts_with($link, self::HTTP)
            && !preg_match(self::WHITESPACE_REGEX, $link)
                ? '<'.htmlspecialchars($link).'>'
                : self::createMarkdownLink($link);
    }

    private static function createMarkdownLink(string $link): string
    {
        $parts = preg_split(self::WHITESPACE_REGEX, $link, 2);
        $url = $parts[0];
        $title = $parts[1] ?? $url;

        return '['.htmlspecialchars($title).']('.htmlspecialchars($url).')';
    }
}

final class ListLine implements LineTypeInterface
{
    private const PREFIX = '* ';

    public static function isType(string $line): bool
    {
        return str_starts_with($line, self::PREFIX);
    }

    public static function convert(string $line): string
    {
        return htmlspecialchars($line);
    }
}

final class ParagraphLine implements LineTypeInterface
{
    public static function isType(string $line): bool
    {
        // This is the default case.
        return true;
    }

    public static function convert(string $line): string
    {
        return htmlspecialchars($line);
    }
}

final class PreformattedLine implements LineTypeInterface
{
    public static function isType(string $line): bool
    {
        // This is handled separately in the main converter.
        return false;
    }

    public static function convert(string $line): string
    {
        return htmlspecialchars($line);
    }
}

final class PreformattedToggleLine implements LineTypeInterface
{
    private const PREFIX = '```';

    public static function isType(string $line): bool
    {
        return str_starts_with($line, self::PREFIX);
    }

    public static function convert(string $line): string
    {
        return self::PREFIX;
    }
}

final class GemtextToMarkdownConverter
{
    private const LINE_TYPES = [
        BlankLine::class,
        BlockquoteLine::class,
        HeadingLine::class,
        LinkLine::class,
        ListLine::class,
        PreformattedToggleLine::class,
        // This should be last since it's the default case.
        ParagraphLine::class,
    ];

    public function __construct(
        private string $separator = '<br>'
    ) {
    }

    public function convert(string $input): string
    {
        $lines = explode("\n", $input);
        $output = [];
        $inPreformatted = false;
        $previousLineType = BlankLine::class;

        foreach ($lines as $line) {
            [$lineType, $convertedLine] = $this->convertLine($line, $inPreformatted);

            if ($lineType === PreformattedToggleLine::class) {
                $inPreformatted = !$inPreformatted;
            }

            $this->addLineWithSpacing($output, $convertedLine, $lineType, $previousLineType);
            $previousLineType = $lineType;
        }

        return implode("\n", $output);
    }

    private function convertLine(string $line, bool $inPreformatted): array
    {
        if (PreformattedToggleLine::isType($line)) {
            return [
                PreformattedToggleLine::class,
                $inPreformatted ? PreformattedToggleLine::convert($line) : $line,
            ];
        }

        if ($inPreformatted) {
            return [PreformattedLine::class, PreformattedLine::convert($line)];
        }

        $lineType = $this->getLineType($line);

        return [$lineType, $lineType::convert(rtrim($line))];
    }

    private function getLineType(string $line): string
    {
        foreach (self::LINE_TYPES as $lineType) {
            if ($lineType::isType($line)) {
                return $lineType;
            }
        }

        throw new UnexpectedValueException('Unexpected line type');
    }

    private function addLineWithSpacing(array &$output, string $line, string $lineType, string $previousLineType): void
    {
        if ($this->shouldPrependSeparator($lineType, $previousLineType)) {
            $line = $this->separator.$line;
        } elseif ($this->shouldAddBlankLine($lineType, $previousLineType)) {
            $output[] = '';
        }

        $output[] = $line;
    }

    private function shouldAddBlankLine(string $currentLineType, string $previousLineType): bool
    {
        return ($currentLineType === $previousLineType
            && ($currentLineType === BlockquoteLine::class
                || $currentLineType === ParagraphLine::class))
            || ($currentLineType !== $previousLineType
                && $currentLineType !== BlankLine::class
                && $currentLineType !== PreformattedLine::class
                && $previousLineType !== BlankLine::class
                && $previousLineType !== PreformattedLine::class);
    }

    private function shouldPrependSeparator(string $currentLineType, string $previousLineType): bool
    {
        return $currentLineType === LinkLine::class
            && ($previousLineType === LinkLine::class
                || $previousLineType === ParagraphLine::class);
    }
}

if ($argv && $argv[0] && realpath($argv[0]) === __FILE__) {
    $converter = new GemtextToMarkdownConverter();
    $input = stream_get_contents(STDIN);
    $output = $converter->convert($input);
    echo $output;
}
