# Tiki Manager

## Installation

There are three options

Via WikiSuite:
https://wikisuite.org/How-to-install-WikiSuite

Standalone
https://doc.tiki.org/Manager#Installation

As a Tiki Package
https://doc.tiki.org/Tiki-Manager-Package


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

## Contributing

Thanks for your interest in contributing! Have a look through our [issues](https://gitlab.com/tikiwiki/tiki-manager/issues) section and submit a merge request.
