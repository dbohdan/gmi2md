test:
  php gmi2md.php < tests/input.gmi > tests/output.md
  diff tests/reference.md tests/output.md
