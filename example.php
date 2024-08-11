<?php

include 'gmi2md.php';

$converter = new GemtextToMarkdownConverter();
echo $converter->convert(file_get_contents('tests/input.gmi'));
