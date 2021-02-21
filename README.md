# VScout

A pen testing tool to evaluate firewall rules.

## Important
Use this at your own risk! Since **IPChanger** messes with your network settings there might be situations where your networking breaks. I've had issues with that in the past when using **TorGhost**. My fork fixes some of them, but there might be more lurking. Consider yourself warned!  

This will remove any existing installation of `apache2`!

## Prerequisites
- PHP7.4+ with the `sqlite3` extension
- be logged in as `root`!
- a Debian-based Linux distro (works on Ubuntu 20.04, WSL certainly doesn't work, everything else is untested)
- your server needs to have a second network interface that you reach while the primary interface is used for the TOR connection (does not apply with `TOR_MODE = false`)

## Installation
First of all you should prepare your server with the basic requirements:
```
apt update
apt upgrade
apt install php
apt install php-sqlite3
```

Now get the repo:
```
cd /usr/local/src
git clone https://github.com/Toxyl/vscout.git
cd vscout
chmod +x install
```

Configure everything:
```
nano classes/Config-VScout.php 
nano data/config.json
nano data/domains.txt
nano data/urls.txt
```

And finally install:
```
./install
```

You will be asked for the public IP of the server. Make sure to fill in the correct one as this will be used to prevent requests if `TOR_MODE = true` and your IP has not been changed correctly.  

After the install procedure has finished you will find the compiled **VScout** in `/usr/local/bin/` (the `BIN_DIR` constant). The `config.json` can be found in `/usr/local/lib/vscout/` (the `DATA_DIR` constant). The installation procedure will also create a symlink named `index.php` in `/var/www/html/` (the `WEB_DIR` constant).

## Templates
**VScout** operates completely autonomous but it does need data to work with. 
That data is generated from a couple of files in the data directory (`/usr/local/lib/vscout` by default). 

### Available Placeholders
#### Variable Blocks
```
[DOMAINS:3]
{                  
   ...             
}
```

List of domains that are made available through the `$DOMAINS3` variable 
defined in domains.txt in block 3. Each will be checked for usability.
Only active domains that are not firewalled (unless `CHECK_FIREWALLS = false`) will be added to the system.

```
[BACKEND:https:7]                                                                          
{                                                                                          
    hello/world[int:3]                                                                     
    ?some-random-var=[int:3]                                                               
}                                                                                          
```

Use the above to create blocks of URL patterns using one of the `$DOMAINS*` variables.       
The second parameter can be http or https and defines the protocol to use.                 
The third parameter defines from which domain set to read the domain name.                 
The example would parse to:            

```                                                  
https://[$DOMAINS:7]/hello/world[int:3]                                                       
https://[$DOMAINS:7]/?some-random-var=[int:3]
```

#### Misc. Variable Shorthands
```
[$DOMAINS:3]     = random domain from the domains defined in domains.txt in block 3 
[PTA|int:6|a,b]  = random placeholder (separate with pipes)
```

#### Random Data From Datasets
```
[USERAGENT]    = random user agent (from useragents.txt)
[DOMAIN]       = random spammer domain (from domains.txt)
[USER]         = random user name (from usernames.txt)
[@]            = random fake email ([USER]@[DOMAIN])
[WORD]         = random word (from wordlists.txt)
```

#### Generated Random Data
```
[#UUID]        = random UUID (xxxxxxxx-xxxx-xxxx-xxxxxxxxxxxx)
[#56]          = random 56-characters hash 
[int:6]        = random 6-characters integer (zero-padded)
[str:6]        = random 6-characters lowercase string (a-z) 
[strU:6]       = random 6-characters uppercase string (A-Z) 
[strR:6]       = random 6-characters mixed-case string (a-z, A-Z) 
[mix:6]        = random 6-characters lowercase alphanumeric string (a-z, 0-9) 
[mixU:6]       = random 6-characters uppercase alphanumeric string (A-Z, 0-9) 
[mixR:6]       = random 6-characters mixed-case alphanumeric string (a-z, A-Z, 0-9) 
[10-500]       = random value between 10 and 500 (inclusive)
[a,b,c]        = random value from the list 
```

#### Vulnerabilities
Vulnerabilities are read from wordlists in the `vuln` data directory (by default at `/usr/local/lib/vscout/vuln/`). The file names define the placeholder names, e.g. `[XSS]` tells us that the data is read from `/usr/local/lib/vscout/vuln/XSS.txt`.
```
[VULN]         = random vulnerability
[PTA]          = random path traversal attack
[SQLi]         = random SQLi attack
[XSS]          = random XSS attack
```

## Acknowledgments 
**IPChanger** is a fork of http://github.com/SusmithKrishnan/torghost that I modified to suit the needs of **VScout**.
