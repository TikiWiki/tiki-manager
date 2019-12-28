# Tiki Manager

## Installation

The preferred way to install this extension is through composer.
To install Tiki Manager it is required to have [composer](https://getcomposer.org/download/) installed.

```
php composer.phar install
```

## Configuration

To easily configure Tiki-Manager application, copy `.env.dist` file to `.env` and insert your configurations for the uncommented (#) entries.

### Version Control System
Tiki Manager by default uses git and public repository. If you want o use SVN as your default vcs or another repository please add the following lines to your `.env` file.
```
DEFAULT_VCS=svn
GIT_TIKIWIKI_URI=<CUSTOM_GIT_REPOSITORY_URL>
SVN_TIKIWIKI_URI=<CUSTOM_SVN_REPOSITORY_URL>
```

#### Behind Proxy or without internet connection

Tiki Manager is able to use Tiki's distributed version packages as an alternative when there is no connection to external servers like gitlab or sourceforge.

Setting the default VCS to `src`, Tiki Manager will use existing packages in the data/tiki_src folder (default). 
```
DEFAULT_VCS=src
```

Download the distributed Tiki packages, from https://sourceforge.net/projects/tikiwiki/files/, and save them into data/tiki_src folder.

### Email settings
To configure Tiki-Manager email sender address add the following line to your `.env` file.
```
FROM_EMAIL_ADDRESS=<SENDER_EMAIL_ADDRESS>
```

#### Configure SMTP Server
By default Tiki-Manager user sendmail to send email notifications. If you intend to use SMTP instead add the following lines to your `.env` file.
```
SMTP_HOST=<SERVER_ADDRESS>
SMTP_PORT=<SERVER_PORT>
SMTP_USER=(optional if authentication is required)
SMTP_PASS=(optional if authentication is required)
```

### Web Manager settings
If you want to setup a default folder to install your web manager or apache user:group are different than apache:apache you can add the following settings to your `.env` file.
```
WWW_PATH=<WEB_MANAGER_FOLDER>
WWW_USER=<APACHE_USER>
WWW_GROUP=<APACHE_GROUP>
```

## Documentation

Documentation is at [Tiki Documentation - Manager](https://doc.tiki.org/Manager).

## Contributing

Thanks for your interest in contributing! Have a look through our [issues](https://gitlab.com/tikiwiki/tiki-manager/issues) section and submit a merge request.
