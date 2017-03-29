
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