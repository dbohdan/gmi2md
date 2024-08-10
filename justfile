default: test

format:
  pretty-php --one-true-brace-style --operators-first gmi2md.php

test:
  php gmi2md.php < tests/input.gmi > tests/output.md
  diff tests/reference.md tests/output.md
