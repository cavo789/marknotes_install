# Marknotes – Installation script

> Do you want to very quickly install [marknotes](https://github.com/cavo789/marknotes)? This script is for you

[![Logo](https://raw.githubusercontent.com/cavo789/marknotes/master/src/assets/images/marknotes.png)](https://www.marknotes.fr)

## Description

PHP installation script for [marknotes](https://github.com/cavo789/marknotes).

## Table of Contents

- [Install](#install)
- [Remarks](#remarks)
- [License](#license)

## Install

Get a copy of this `install.php` script and save it on your localhost website or on your internet FTP server in his own folder.

Then just access it by URL like, for instance, `http://localhost/install.php`.

![Install](https://raw.githubusercontent.com/cavo789/marknotes_install/master/image/install.png)

Tadaaaa :tada:, it’s done. Was easy no?

Note: this script will ask you which version to download, the `master` version or the `development` one. Once your choice has been made, the script will download the latest version (as a zip file) of marknotes. Once downloaded, the zip file will be unzipped and if everything goes fine, both the zip and this install.php script will be removed during the finalization phase.

## Remarks

This script will work on almost all web hosters since they’ve enabled the `curl` and `ZipArchive` Apache modules. If one of them is not loaded (*you can verify with `phpinfo`*), the script can’t work and then you’ll need to install marknotes manually (see point [3.2 – Hard way](https://github.com/cavo789/marknotes#32-hard-way)).

## Contribute

PRs accepted.

## License

[MIT](LICENSE)
