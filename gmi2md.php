#!/usr/bin/env php
<?php declare(strict_types=1);

enum LineType: string
{
    case Backticks = 'backticks';
    case Blank = 'blank';
    case Heading = 'heading';
    case Link = 'link';
    case List = 'list';
    case Paragraph = 'paragraph';
    case Preformatted = 'preformatted';
    case Quote = 'quote';
}

final class GemtextToMarkdownConverter
{
    private const BR = '<br>';
    private const HEADING = '/^#{1,3} /';
    private const HTTP = 'http';
    private const LINK_PREFIX = '=>';
    private const LIST_PREFIX = '* ';
    private const PREFORMATTED_DELIMITER = '```';
    private const QUOTE_PREFIX = '>';
    private const WHITESPACE = '/[ \t]+/';

    private array $blankLineTypes = [
        LineType::Backticks,
        LineType::Heading,
        LineType::Paragraph
    ];

    private bool $brBeforeLinks;

    public function __construct(bool $brBeforeLinks = false)
    {
        $this->brBeforeLinks = $brBeforeLinks;

        if (!$this->brBeforeLinks) {
            $this->blankLineTypes[] = LineType::Link;
        }
    }

    public function convert(string $input): string
    {
        $lines = explode("\n", $input);
        $output = [];
        $inPreformatted = false;
        $previousLineType = LineType::Blank;

        foreach ($lines as $line) {
            [$lineType, $convertedLine] = $this->convertLine($line, $inPreformatted);

            if ($lineType === LineType::Backticks) {
                $inPreformatted = !$inPreformatted;
            }

            $this->addLineWithSpacing($output, $convertedLine, $lineType, $previousLineType);
            $previousLineType = $lineType;
        }

        return implode("\n", $output);
    }

    private function convertLine(string $line, bool $inPreformatted): array
    {
        $trimmedLine = rtrim($line);

        if ($trimmedLine === self::PREFORMATTED_DELIMITER) {
            return [LineType::Backticks, $line];
        }

        if ($inPreformatted) {
            return [LineType::Preformatted, $line];
        }

        $lineType = $this->lineType($trimmedLine);
        $convertedLine = $lineType === LineType::Link ? $this->convertLink($trimmedLine) : $trimmedLine;

        return [$lineType, $convertedLine];
    }

    private function addLineWithSpacing(array &$output, string $line, LineType $lineType, LineType $previousLineType): void
    {
        if ($this->shouldAddBlankLine($lineType, $previousLineType)) {
            $output[] = '';
        }

        if ($this->shouldAddBrPrefix($lineType, $previousLineType)) {
            $line = self::BR . $line;
        }

        $output[] = $line;
    }

    private function shouldAddBlankLine(LineType $currentLineType, LineType $previousLineType): bool
    {
        return $previousLineType !== LineType::Blank &&
            $currentLineType !== LineType::Blank &&
            in_array($previousLineType, $this->blankLineTypes, true) &&
            in_array($currentLineType, $this->blankLineTypes, true);
    }

    private function shouldAddBrPrefix(LineType $currentLineType, LineType $previousLineType): bool
    {
        return $this->brBeforeLinks &&
            $currentLineType === LineType::Link &&
            ($previousLineType === LineType::Link ||
                $previousLineType === LineType::Paragraph);
    }

    private function lineType(string $line): LineType
    {
        return match (true) {
            $line === '' => LineType::Blank,
            $this->isHeading($line) => LineType::Heading,
            $this->isLink($line) => LineType::Link,
            $this->isList($line) => LineType::List,
            $this->isQuote($line) => LineType::Quote,
            default => LineType::Paragraph,
        };
    }

    private function isHeading(string $line): bool
    {
        return (bool) preg_match(self::HEADING, $line);
    }

    private function isLink(string $line): bool
    {
        return str_starts_with($line, self::LINK_PREFIX);
    }

    private function convertLink(string $line): string
    {
        $link = trim(substr($line, strlen(self::LIST_PREFIX)));

        if (str_starts_with($link, self::HTTP) &&
                !preg_match(self::WHITESPACE, $link)) {
            return "<$link>";
        }

        $parts = preg_split(self::WHITESPACE, $link, 2);
        $url = $parts[0];
        $title = $parts[1] ?? $url;

        return "[$title]($url)";
    }

    private function isList(string $line): bool
    {
        return str_starts_with($line, self::LIST_PREFIX);
    }

    private function isQuote(string $line): bool
    {
        return str_starts_with($line, self::QUOTE_PREFIX);
    }
}

$converter = new GemtextToMarkdownConverter(true);
$input = stream_get_contents(STDIN);
$output = $converter->convert($input);
echo $output;
