#!/usr/bin/python
# -*- coding: utf-8 -*-

import os
import sys
import getopt
from requests import get
import subprocess
import time
import signal
from stem import Signal
from stem.control import Controller
from packaging import version
import time

VERSION = "3.1.2"

IP_API = "http://checkip.amazonaws.com/"

class bcolors:

    BLUE = '\033[94m'
    GREEN = '\033[92m'
    RED = '\033[31m'
    YELLOW = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    BGRED = '\033[41m'
    WHITE = '\033[37m'

def t():
    current_time = time.localtime()
    ctime = time.strftime('%H:%M:%S', current_time)
    return '[' + ctime + ']'


def sigint_handler(signum, frame):
    print("User interrupt! Shutting down.")
    stop()


def logo():
    print(bcolors.RED + bcolors.BOLD)
    print("""
    ________                                  
   /  _/ __ \                                 
   / // /_/ /                                 
 _/ // ____/___                               
/___/_/ ____/ /_  ____ _____  ____ ____  _____
     / /   / __ \/ __ `/ __ \/ __ `/ _ \/ ___/
    / /___/ / / / /_/ / / / / /_/ /  __/ /    
    \____/_/ /_/\__,_/_/ /_/\__, /\___/_/     
                           /____/ v{V}            
	            
    basedOnTorGhost(
        github.com/SusmithKrishnan/torghost
    );
    """.format(V=VERSION))
    print(bcolors.ENDC)

def usage():
    logo()
    print("""
    IPChanger usage:
    -s    --start       Start IPChanger
    -r    --switch      Request new exit node
    -i    --ip          Print current IP address
    -x    --stop        Stop IPChanger
    -h    --help        Print this screen, see --help

    Current IP: %s
    """ % ip())

    sys.exit()

strTorConfig = \
    """
VirtualAddrNetwork 10.0.0.0/10
AutomapHostsOnResolve 1
TransPort 9040
DNSPort 5353
ControlPort 9051
RunAsDaemon 1
"""

strResolveConfDisabled = \
    """
nameserver 8.8.8.8
nameserver 8.8.4.4
"""

strResolveConfEnabled = \
    """
nameserver 127.0.0.1
"""

fIPChanger       = '/etc/tor/ipchanger_rc'
fIPChangerRealIP = '/etc/tor/ipchanger_realip'
fResolveConf     = '/etc/resolv.conf'

strCurrentIP     = 'IP is empty!';

def ip():
    while True:
        try:
            strCurrentIP = get(IP_API).text.strip()
        except:
            continue
        break
    return strCurrentIP

def check_ip():
    if ip() in open(fIPChangerRealIP).read():
        return False
    return True

def check_root():
    if os.geteuid() != 0:
        print("Only root has the power!")
        sys.exit(0)

def print_info(msg):
    print(t() + bcolors.WHITE   + ' [INFO]    '  + bcolors.ENDC + msg + bcolors.ENDC)

def print_success(msg):
    print(t() + bcolors.GREEN   + ' [DONE]    '  + bcolors.ENDC + msg + bcolors.ENDC)

def print_warning(msg):
    print(t() + bcolors.YELLOW  + ' [WARNING] '  + bcolors.ENDC + msg + bcolors.ENDC)

def print_error(msg):
    print(t() + bcolors.RED     + ' [ERROR]   '  + bcolors.ENDC + msg + bcolors.ENDC)

def current_ip():
    if check_ip() is False:
        print_error(  'Not connected to Tor! Current IP: ' + bcolors.YELLOW + ip())
    else:
        print_success('Connected to Tor! Current IP: ' + bcolors.YELLOW + ip())

def stop():
    if check_ip() is True:
        print_info('Stopping IPChanger...')
        with open(fResolveConf, 'w') as fileResolveConf:
            print_info('Resetting resolv.conf...')
            fileResolveConf.write(strResolveConfDisabled)
            
        print_info('Flushing iptables...')
        IpFlush = \
            """
    	iptables-legacy -P INPUT ACCEPT
    	iptables-legacy -P FORWARD ACCEPT
    	iptables-legacy -P OUTPUT ACCEPT
    	iptables-legacy -t nat -F
    	iptables-legacy -t mangle -F
    	iptables-legacy -F
    	iptables-legacy -X
        iptables-legacy -A INPUT -p tcp --dport 4949 -j ACCEPT
    	"""
        os.system(IpFlush)
        os.system('fuser -k 9051/tcp > /dev/null 2>&1')
        
        print_info('Restarting network-manager...')
        os.system('service network-manager restart')
            
        print_info('Verifying connection...')
        time.sleep(5)
        if check_ip() is True:
            print_error('Failed to disconnect properly, retrying...')
            stop()
        else:
            print_success('Current IP: ' + bcolors.YELLOW + ip())
    else:
        print_success('Already disconnected.')

def new_ip():
    print_info('Requesting new circuit...'),
    time.sleep(7)
    with Controller.from_port(port=9051) as controller:
        controller.authenticate()
        controller.signal(Signal.NEWNYM)

    if check_ip() is False:
        print_info('Failed to get new IP, retrying...'),
        new_ip()
    else:
        print_success('Current IP: ' + bcolors.YELLOW + ip())

def start():
    if check_ip() is False:
        with open(fIPChanger, 'w') as fileIPChanger:
            print_info('Writing Tor config...')
            fileIPChanger.write(strTorConfig)
        with open(fResolveConf, 'w') as fileResolveConf:
            print_info('Writing resolv.conf...')
            fileResolveConf.write(strResolveConfEnabled)

        print_info('Stopping Tor service...')
        os.system('systemctl stop tor')
        os.system('fuser -k 9051/tcp > /dev/null 2>&1')
        
        print_info('Starting new Tor daemon...')
        os.system('sudo -u debian-tor tor -f %s > /dev/null' % fIPChanger)
        
        print_info('Setting up iptables rules...')
        iptables_rules = \
            """
        NON_TOR="192.168.1.0/24 192.168.0.0/24"
        TOR_UID=%s
        TRANS_PORT="9040"

        iptables-legacy -F
        iptables-legacy -t nat -F

        iptables-legacy -t nat -A OUTPUT -m owner --uid-owner $TOR_UID -j RETURN
        iptables-legacy -t nat -A OUTPUT -p udp --dport 53 -j REDIRECT --to-ports 5353
        for NET in $NON_TOR 127.0.0.0/9 127.128.0.0/10; do
         iptables-legacy -t nat -A OUTPUT -d $NET -j RETURN
        done
        iptables-legacy -t nat -A OUTPUT -p tcp --syn -j REDIRECT --to-ports $TRANS_PORT

        iptables-legacy -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
        for NET in $NON_TOR 127.0.0.0/8; do
         iptables-legacy -A OUTPUT -d $NET -j ACCEPT
        done
        iptables-legacy -A OUTPUT -m owner --uid-owner $TOR_UID -j ACCEPT
        iptables-legacy -A OUTPUT -j REJECT
        """ \
            % subprocess.getoutput('id -ur debian-tor')
        os.system(iptables_rules)
            
        if check_ip() is False:
            print_error('Failed to get new IP! Will retry.')
            start()
        else:
            print_success('Current IP: ' + bcolors.YELLOW + ip())
            signal.signal(signal.SIGINT, sigint_handler)
            starttime = time.time()
            while True:
                time.sleep(300.0 - ((time.time() - starttime) % 300.0))
                print_info('Requesting new IP...')
                new_ip()
    else:
        new_ip()

def main():
    check_root()
    if len(sys.argv) <= 1:
        usage()
    try:
        (opts, args) = getopt.getopt(sys.argv[1:], 'srxhi', [
            'start', 'stop', 'switch', 'help', 'ip'])
    except (getopt.GetoptError):
        usage()
        sys.exit(2)
    for (o, a) in opts:
        if o in ('-h', '--help'):
            usage()
        elif o in ('-s', '--start'):
            start()
        elif o in ('-i', '--ip'):
            current_ip()
        elif o in ('-x', '--stop'):
            stop()
        elif o in ('-r', '--switch'):
            new_ip()
        else:
            usage()


if __name__ == '__main__':
    main()
