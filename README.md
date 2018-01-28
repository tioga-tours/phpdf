# PhPdf

For now it is a thin wrapper around wkhtmltopdf, like Knplabs\snappy. By default
it includes all wkhtmltopdf dependencies for all platforms. It knows which options 
are valid and validates them directly, so debuggin is easier.

Also I did some research about the header and footer html properties. Those were
quite buggy with wkhtmltopdf. Plain html tags that are passed in, are
wrapped inside some styled html document. NOTE: some styles may still mess
up your header and footer.

It also supports async generation of PDF files.

## Usage

```
<?php
$pdf = new \PhPdf\WkhtmlToPdf();
$pdf->setOption('margin-top', '30mm');
$pdf->setOption('header-html', <<<HTML
<div style="text-align:center">
<img src="https://ievgensaxblog.files.wordpress.com/2017/11/github-logo.png?w=636" style="width: 300px;" />
</div>'
HTML
    );
$pdf->addHtml('<html><head></head><body><h1>Content</h1></body></html>');
$pdf->addUrl('https://github.com');
$pdf->generate('/home/user/mydoc.pdf');

// Or async and create temporary file
$pdf->generate(null, true);
// do other stuff

$tmpFilePath = $pdf->wait();

if ($tmpFilePath === false) {
    throw new \Exception('There was an error generating the PDF');
    // Check error using $pdf->getErrorOutput();
|

// Do something with the tmpFilePath, it will be deleted after 
// this script is done

```