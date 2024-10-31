# Tiki Manager

## Installation

There are four options

Via WikiSuite:
https://wikisuite.org/How-to-install-WikiSuite

Standalone
https://doc.tiki.org/Manager#Installation

As a Tiki Package
https://doc.tiki.org/Tiki-Manager-Package

Using a PHP Archive file (tiki-manager.phar).
See later in this README file for more informations.

## Configuration

To easily configure Tiki Manager, copy the `.env.dist` file to `.env` and insert your configurations for the uncommented (#) entries.

### Version Control System
Tiki Manager by default uses Git and the main public repository at https://gitlab.com/tikiwiki/tiki. If you want to use SVN as your default vcs (but you shouldn't as SVN is deprecated) or another repository please add the following lines to your `.env` file.
```
DEFAULT_VCS=svn
GIT_TIKIWIKI_URI=<CUSTOM_GIT_REPOSITORY_URL>
SVN_TIKIWIKI_URI=<CUSTOM_SVN_REPOSITORY_URL>
```

#### Behind a Proxy or without internet connection

Tiki Manager is able to use Tiki's distributed version packages as an alternative when there is no connection to external servers like GitLab or SourceForge.

Setting the default VCS to `src`, Tiki Manager will use existing packages in the data/tiki_src folder (default).
```
DEFAULT_VCS=src
```

Download the distributed Tiki packages, from https://sourceforge.net/projects/tikiwiki/files/, and save them into data/tiki_src folder.

### Email settings
To configure Tiki Manager email sender address add the following line to your `.env` file.
```
FROM_EMAIL_ADDRESS=<SENDER_EMAIL_ADDRESS>
```

#### Configure SMTP Server
By default Tiki Manager uses sendmail to send email notifications. If you intend to use SMTP instead, add the following lines to your `.env` file.
```
SMTP_HOST=<SERVER_ADDRESS>
SMTP_PORT=<SERVER_PORT>
SMTP_USER=(optional if authentication is required)
SMTP_PASS=(optional if authentication is required)
```

## Documentation

Documentation is at [Tiki Documentation - Manager](https://doc.tiki.org/Manager).

## Releases

Tiki-manager releases are available on [Gitlab - Tiki-manager releases](https://gitlab.com/tikiwiki/tiki-manager/-/releases).

Those release are automatically build by the Gitlab CI/CD when a new version tag is added.
Only project maintainers can add such tags.

You can access the latest release description by using this permalink:
https://gitlab.com/tikiwiki/tiki-manager/-/releases/permalink/latest

## Using the PHP Archive tiki-manager.phar

You can download a PHP Archive file to use `tiki-manager.phar` as a single executable.

Those archive are available in following places:

* in the [Gitlab Package Registry](https://gitlab.com/tikiwiki/tiki-manager/-/packages)
* as assets on [Gitlab releases](https://gitlab.com/tikiwiki/tiki-manager/-/releases)

To get the latest released `tiki-manager.phar` you can use this permalink:
https://gitlab.com/tikiwiki/tiki-manager/-/releases/permalink/latest/downloads/tiki-manager.phar

Just download it in a working directory, and run it using `php tiki-manager.phar`, or make the file executable and run `./tiki-manager.php`.

You can customize the configuration by adding a `.env` file in the same directory as `tiki-manager.phar`.

> Note: for now there is a bug with `tiki-manager.phar`: it does not download composer automatically. So you have to install it yourself for now.

> Note: avoid putting the file in a directory where you have another Symfony project source files. It could interfere.

Here is an example on how you can initiate `tiki-manager.phar`:

```bash
mkdir working_dir
cd working_dir
wget https://gitlab.com/tikiwiki/tiki-manager/-/releases/permalink/latest/downloads/tiki-manager.phar
chmod u+x tiki-manager.phar
./tiki-manager.phar -n list
```

## Contributing

Thanks for your interest in contributing! Have a look through our [issues](https://gitlab.com/tikiwiki/tiki-manager/issues) section and submit a merge request.
