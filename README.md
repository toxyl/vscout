# VScout

A pen testing tool to evaluate firewall rules.

## WARNING!
Use this at your own risk!  
  
Using this on a virtual machine (or a separate server) is highly recommended as the installer will make a couple of changes to the OS installation such as removing (without confirmation!) any existing installation of `apache2` and replacing it with `nginx`. It will also install the **IPChanger** service which is used to route every connection through Tor and switch your IP every 5 minutes. Since **IPChanger** messes with your network settings there might be situations where your networking breaks. I've had issues with that in the past when using **TorGhost**. My fork fixes some of them, but there might be more lurking. Consider yourself warned!  
    
This document is a work in progress and will probably miss some things and have a few bugs itself, feel free to suggest corrections / additions.

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
Note: make sure to add your IPs to the server whitelist in `data/config.json`, otherwise you will not be able to access the dashboard.
Also make sure to add domains to test in `data/domains.txt`, else you will have **VScout** eating up all resources to scan itself. ;)  
Read the [Templates](#templates) section for more information about editing template files like `domains.txt`. And take a look at the files themselves, they come with 'documentation'.

And finally install:
```
./install
```

You will be asked for the public IP of the server. Make sure to fill in the correct one as this will be used to prevent requests if `TOR_MODE = true` and your IP has not been changed correctly.  

After the install procedure has finished you will find the compiled **VScout** in `/usr/local/bin/` (the `BIN_DIR` constant). The `config.json` can be found in `/usr/local/lib/vscout/` (the `DATA_DIR` constant). The installation procedure will also create a symlink named `index.php` in `/var/www/html/` (the `WEB_DIR` constant).

## How To Use
After installation NginX should have started serving the dashboard on `http://127.0.0.1:80`.  
If you installed with `TOR_MODE = true` the IPChanger service should have started as well. In that scenario you will **not** be able to reach your server with its public IP once the installation has finished, so make sure you have access to it via a local network. In my test setup I used a second server in the same local network to act as a reverse proxy to the local IP of the **VScout** server, so I can access the dashboard even when the **VScout** server is connected to Tor. 

Using the dashboard you can see the current configuration and stats and test URL patterns and analyze the responses.  

If you want to run it continuously you have to login to your server and start the service: `service vscout start`.

### Daemon And Workers
**VScout** automatically manages workers, i.e. it will spawn and kill them as needed. It does so based on the average load of the system and the configuration in `config.json`.  You can define the minimum amount of workers the daemon will always keep running and the maximum it will spawn. The daemon will spawn a worker if the 5m average load falls below the `workers -> spawn_threshold` and will kill one if the 5m average load goes beyond the `workers -> destroy_threshold`. It checks every `workers -> update_time` seconds whether it needs to spawn or kill a worker. If the 5m average load is between the two it will leave the workers alone.  
The daemon will also update the stats every `database -> stats_update_time` seconds. You can set this value quite low in the beginning but as your database grows you will have to increase it to not overload your server. 

### Logging  
Once the daemon has started you can follow its output using `journalctl -u vscout -f`. 
By default you will only see messages when workers are added or removed and when stats have been updated. If you want to see all URLs VScout is testing you have to enable `log -> responses` in `config.json`. It will then log the URL and a few pieces of information about the response like status code and target's IP. The format for these messages is:
```
[VS Worker <pid of daemon>-<pid of worker>] <mark> <status> [<ip>] <url>
``` 
| Component         | Description                                                                                                                                                                                                                                                       |
|-------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `<pid of daemon>` | This is the PID of the daemon process.                                                                                                                                                                                                                            |
| `<pid of worker>` | This is the PID of the worker process.                                                                                                                                                                                                                            |
| `<mark>`          | `OK` (status code was 200), <br>`E!` (status was 500, 501, 502 or 503), <br>`FW` (status was 403 or 429 which could indicate a firewall), <br>`  ` (status code was 404), <br>`D!` (could not resolve the IP of the domain) or <br>` ?` (something else happened) |
| `<status>`        | This usually holds the status code, but for some visual cues I decided to replace some: <br>`---` for 404 status codes, <br>`···` for unknown results and <br>`   ` for dead domains                                                                              |
| `<ip>`            | The IP of the server serving the domain. It is formatted so that the blocks align across log messages.                                                                                                                                                            |
| `<url>`           | The URL tested. Be aware that it can contain vulnerabilities that might work on your own machine!                                                                                                                                                                 |

### More On CLI
Just execute `vscout` to see a list of available commands, for example `vscout stats 1` lets you keep track of the stats on CLI, it will update every `database -> stats_update_time` seconds.  
For any command you can get help and usage examples by executing `vscout <command> \?`. 

## <a name="templates"></a> Templates
**VScout** can operate completely autonomous but it does need data to work with. That data is generated from a couple of files in the data directory (`/usr/local/lib/vscout` by default). These files are loaded and parsed every time a worker spawns, so changes to them will not immediately take effect but will bleed in as workers are removed. If you want your changes to take effect immediately you need to run `service vscout restart`.

Template files can contain comments (lines prefixed with `#` are treated as such) and you can use spaces for indentation purposes in URL patterns, so that it's easy to visually align patterns.

So here's a rundown of the files involved in order of relevance:  

### `domains.txt`
This file holds the domains you want to test and allows two notations:
`example.com`, i.e. one domain per line, or:
``` 
[DOMAINS:3]
{                  
   example.com
   subdomain.example.com             
}
```
The latter will create a variable that you can access as `[$DOMAINS3]` (note the dollar and the missing colon) when building URL patterns. They will then parse to random domains from the set.
The domains will be loaded and if no record of them exists in the database **VScout** will check if it can resolve them to an IP[^1] and checks if the domain is firewalled[^2]. If a record already exists there will be no IP lookup nor a firewall check. You can however force a reevaluation of all domains in the database by running the command `vscout domains.rescan`. 

[^1]: uses `tor-resolve` if `TOR_MODE = true` (to avoid DNS leaks) and otherwise `dig` is used
[^2]: if `CHECK_FIREWALLED = true` this does a `HEAD` request and checks if the status code of the response is 403 or 429 which could be indicative of a firewall

### `urls.txt`
This file can also contain two notations, one pattern per lone or blocks of patterns called *backends* which reference domains from blocks in `domain.txt`.
```
[BACKEND:https:3]
{
                   index.php    / ? vulnerable=[VULN]
    some / path  / script.php   / ? vulnerable=[XSS]
                   index.php    / ? vulnerable=[SQLi]
                   index.php    / ? vulnerable=[PTA]
    some / path                 / ? paramA=[mixR:6] & paramB=[#UUID] 
    some / [PTA]                / ? paramA=[mixR:6] & paramB=[#UUID] 
}
```
The structure is almost identical to the domain blocks: `[BACKEND:https:3]` means that the random data generator will combine one of the patterns in the block (one per line) with a random domain from the `[DOMAINS:3]` block and use `https` (only `http` and `https` are available!) as protocol to form a random URL. In these blocks you should not prefix patterns with protocol and domain! That is, however, required for patterns defined outside of blocks.

Read the section [Available Placeholders](#placeholders) for an overview of all the neat placeholders available.

### `user_agents.txt`
Just like always using the same IP a user agent can make you an easy target for a firewall, to avoid that **VScout** generates a random user agent for every request. They are generated from this file. The two patterns contained are made from a small collection of user agents and will in most cases produce user agents that might look legit if you see them scroll by but will prove oddly wrong on closer inspection, like an iPad from Microsoft or Facebook's Mozilla. If you need other/realistic user agents you can simply append them to the file (one user agent per line) or make your own patterns (see documentation in the file itself).

### `user_names.txt`
A list of user names that is used by the `[USER]` placeholder (see below for details).

### `wordlists.txt`
This file contains word lists you can use in URL patterns. Each block will be made available as a variable. For example:
```
[DIRTYWORDS:1] 
{
	oneDirtyWord
	anotherDirtyWord
	aHarmlessWord
}
```
With the above block you could generate a URL pattern like this:
```
https://dirty.example.com/[$DIRYWORDS1]
```
This will randomly pick one of the three words (so a one in three chance that it will be a harmless word) from the list every time this placeholder is encountered. So using it twice in one URL pattern does not mean it will be the same word used.

### `vuln` directoruy
In this directory you find lists of vulnerabilities, currently there are three lists:
- `PTA.txt` is a list of path traversal attacks
- `SQLi.txt` is a list of SQL injection attacks
- `XSS.txt` is, you guessed it, a list of cross side scripting attacks

You can add more lists to this directory if you like, **VScout** will automatically generate a placeholder for it that you can use. The included files, for example, are available as `[PTA]`, `[SQLi]` and `[XSS]` respectively. If you added `MyFancyAttack.txt` **VScout** would generate the placeholder `[MyFancyAttack]`. Data from these files is not parsed further to avoid breaking the vulnerabilities. 

### `blacklist.txt`
This a list of IPs to blacklist. If `TOR_MODE = true` then requests will not be made if the current public IP appears in this list. The dashboard will always prefix your public IP with `[BLACKLISTED]` if it's in this list, even if `TOR_MODE = false`.

### <a name="placeholder"></a>Available Placeholders
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
