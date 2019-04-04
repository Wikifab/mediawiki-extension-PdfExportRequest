
## Installation

Cette extension passe utilises des packages externes pour convertir la page html en PDF

ci-dessous les instruction d'installation valables pour une installation sur Ubuntu 16.04

### installation de xvfb

sudo apt-get install xvfb

### installation de wkhtmltox

sudo apt-get update
sudo apt-get install libxrender1 fontconfig xvfb
wget http://download.gna.org/wkhtmltopdf/0.12/0.12.3/wkhtmltox-0.12.3_linux-generic-amd64.tar.xz -P /tmp/
cd /opt/
sudo tar xf /tmp/wkhtmltox-0.12.3_linux-generic-amd64.tar.xz
sudo ln -s /opt/wkhtmltox/bin/wkhtmltopdf /usr/bin/wkhtmltopdf

## Configuration

### prefixe des fichiers exportés

Il est possible de modifier le prefixe du nom des fichier exporté, en le définissant variable $wfPdfExportPrefix dans la fichier Localsettings.php : (par défault, c'est le nom du wiki)

$wfPdfExportPrefix = $wgSitename;

### wkHtmlToPdf command

You can change the command used to generate pdf in File Localsettings.php by settings this variable : 
  $wgPberWkhtmlToPdfExec 

default value is : 
  $wgPberWkhtmlToPdfExec  = "xvfb-run /usr/bin/wkhtmltopdf"
  
Alternate value can be : 
  $wgPberWkhtmlToPdfExec = "/usr/bin/wkhtmltopdf.sh --load-error-handling ignore --encoding 'utf-8'";
  