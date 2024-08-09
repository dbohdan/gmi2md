#! /usr/bin/env php
<?php declare(strict_types=1);

enum LineType: string
{
    case Backticks = 'backticks';
    case Blank = 'blank';
    case Blockquote = 'blockquote';
    case Heading = 'heading';
    case Link = 'link';
    case List = 'list';
    case Paragraph = 'paragraph';
    case Preformatted = 'preformatted';
}

final class GemtextToMarkdownConverter
{
    private const BACKTICKS_PREFIX = '```';
    private const BLOCKQUOTE_PREFIX = '>';
    private const HEADING = '/^#{1,3} /';
    private const HTTP = 'http';
    private const LINK_PREFIX = '=>';
    private const LIST_PREFIX = '* ';
    private const WHITESPACE = '/[ \t]+/';

    private string $separator;

    public function __construct(string $separator = '<br>')
    {
        $this->separator = $separator;
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

        if (str_starts_with($trimmedLine, self::BACKTICKS_PREFIX)) {
            return [
                LineType::Backticks,
                $inPreformatted ? self::BACKTICKS_PREFIX : $line,
            ];
        }

        if ($inPreformatted) {
            return [LineType::Preformatted, htmlspecialchars($line)];
        }

        $lineType = $this->lineType($trimmedLine);
        $convertedLine = match ($lineType) {
            LineType::Blockquote => $this->convertBlockquote($trimmedLine),
            LineType::Link => $this->convertLink($trimmedLine),
            default => htmlspecialchars($trimmedLine),
        };

        return [$lineType, $convertedLine];
    }

    private function addLineWithSpacing(array &$output, string $line, LineType $lineType, LineType $previousLineType): void
    {
        if ($this->shouldPrependSeparator($lineType, $previousLineType)) {
            $line = $this->separator . $line;
        } elseif ($this->shouldAddBlankLine($lineType, $previousLineType)) {
            $output[] = '';
        }

        $output[] = $line;
    }

    private function shouldAddBlankLine(LineType $currentLineType, LineType $previousLineType): bool
    {
        return ($currentLineType === $previousLineType &&
            ($currentLineType === LineType::Blockquote ||
                $currentLineType === LineType::Paragraph)) ||
            ($currentLineType !== $previousLineType &&
                $currentLineType !== LineType::Blank &&
                $currentLineType !== LineType::Preformatted &&
                $previousLineType !== LineType::Blank &&
                $previousLineType !== LineType::Preformatted);
    }

    private function shouldPrependSeparator(LineType $currentLineType, LineType $previousLineType): bool
    {
        return $currentLineType === LineType::Link &&
            ($previousLineType === LineType::Link ||
                $previousLineType === LineType::Paragraph);
    }

    private function lineType(string $line): LineType
    {
        return match (true) {
            $line === '' => LineType::Blank,
            $this->isBlockquote($line) => LineType::Blockquote,
            $this->isHeading($line) => LineType::Heading,
            $this->isLink($line) => LineType::Link,
            $this->isList($line) => LineType::List,
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

    private function convertBlockquote(string $line): string
    {
        return self::BLOCKQUOTE_PREFIX
            . htmlspecialchars(substr($line, strlen(self::BLOCKQUOTE_PREFIX)));
    }

    private function convertLink(string $line): string
    {
        $link = trim(substr($line, strlen(self::LIST_PREFIX)));

        if (str_starts_with($link, self::HTTP) &&
                !preg_match(self::WHITESPACE, $link)) {
            return '<' . htmlspecialchars($link) . '>';
        }

        $parts = preg_split(self::WHITESPACE, $link, 2);
        $url = $parts[0];
        $title = $parts[1] ?? $url;

        return '[' . htmlspecialchars($title) . '](' . htmlspecialchars($url) . ')';
    }

    private function isList(string $line): bool
    {
        return str_starts_with($line, self::LIST_PREFIX);
    }

    private function isBlockquote(string $line): bool
    {
        return str_starts_with($line, self::BLOCKQUOTE_PREFIX);
    }
}

if ($argv && $argv[0] && realpath($argv[0]) === __FILE__) {
    $converter = new GemtextToMarkdownConverter();
    $input = stream_get_contents(STDIN);
    $output = $converter->convert($input);
    echo $output;
}
