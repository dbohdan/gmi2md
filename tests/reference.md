# gmi2md tests

## Preformatted

### Standalone

```
ls -l
```

### Paragraph before

Foo.

```
ls -l
```

### Paragraph after

```
ls -l
```

Foo.

## Paragraphs

First.

Second.

Third.

## Links

<https://example.com>

<https://example.com/1>
<br><https://example.com/2>
<br><https://example.com/3>

## Mixing paragraphs and links

### Links before

<https://example.com>

First.

Second.

Third.

<https://example.com/1>
<br><https://example.com/2>
<br><https://example.com/3>

First.

Second.

Third.

### Links after

First.

Second.

Third.
<br><https://example.com>

First.

Second.

Third.
<br><https://example.com/1>
<br><https://example.com/2>
<br><https://example.com/3>

### Links in the middle

Foo.

Bar.

Baz.
<br><https://example.com/1>
<br><https://example.com/2>
<br><https://example.com/3>

Qux.

## Lists

* Item 1

* Item 1
* Item 2
* Item 3

## Mixing paragraphs and lists

### List before

* Item 1

First.

Second.

Third.

* Item 1
* Item 2
* Item 3

First.

Second.

Third.

### List after

First.

Second.

Third.

* Item 1

First.

Second.

Third.

* Item 1
* Item 2
* Item 3

### List in the middle

Foo.

Bar.

Baz.

* Item 1
* Item 2
* Item 3

Qux.

## Vertical whitespace

Foo.




Bar.

Baz.
