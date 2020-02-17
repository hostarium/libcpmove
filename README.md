libcpmove - A simple cpmove file parsing library
================================================

This library allows you to easily parse a cpmove archive file and retrieve details from it,
such as the list of domains associated with the account. A cpmove file is the file generated
by cPanel's backup wizard.

Install
-------
Use composer:
```composer require hostarium\libcpmove```

Usage
-----
To use simply create a new `Hostarium\CPMove` object, providing the path to your cpmove file

`$cp = new Hostarium\CPMove('/path/to/backup-user.tar.gz');`

Functions
---------

All functions, including the constructor, throw `Hostarium\HostariumException` on failure.

`getDomains(bool $mainOnly = false)`  
getDomains() will return either an array containing the following keys:
- Main Domain (main_domain)
- Addon Domains (addon_domains)
- Parked Domains (parked_domains)
- Subdomains (sub_domains)

or a string of the main domain if $mainOnly is set to true

`getSQLDatabases()`  
getSQLDatabases() will return an array of mySQL databases, or an empty array 
if no databases are found in the archive

`getHomePath()`  
getHomePath() will return a string containing the absolute path to the account's
home directory, minus a trailing slash

`getMailboxes()`  
getMailboxes() will return an array of mailboxes, or an empty array 
if no mailboxes are found in the archive

License
-------
This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0. 
Please see [LICENSE](./LICENSE) and [NOTICE](./NOTICE) for more information