# snap-wkhtmltopdf-multi

This package installs all variants of wkhtmltopdf to support multi platform pdf 
creation. It combines these packages:

- h4cc/wkhtmltopdf-amd64
- h4cc/wkhtmltopdf-i386
- wemersonjanuario/wkhtmltopdf-windows
- knplabs/knp-snappy

Also it detects if it is installed on the operating system. It will prefer to use that.
