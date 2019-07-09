# Tiki Manager

## Installation

The preferred way to install this extension is through composer.
To install Tiki Manager it is required to have [composer](https://getcomposer.org/download/) installed.

```
php composer.phar install
```

## Configuration

To easily configure Tiki-Manager application, copy `.env.dist` file to `.env` and insert your configurations for the uncommented (#) entries.

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

## Documentation

Documentation is at [Tiki Documentation - Manager](https://doc.tiki.org/Manager).

## Contributing

Thanks for your interest in contributing! Have a look through our [issues](https://gitlab.com/tikiwiki/tiki-manager/issues) section and submit a merge request.
