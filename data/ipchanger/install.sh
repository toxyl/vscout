#!/bin/bash
echo 'Installing Python dependencies...'
python3.9 -m pip install stem
python3.9 -m pip install packaging
python3.9 -m pip install -r requirements.txt 

echo 'Building binary...'
mkdir -p build
cd build
cython3 ../ipchanger.py --embed -o ipchanger.c --verbose
if [ $? -eq 0 ]; then
    echo 'C code generated!'
else
    echo 'Build failed. Unable to generate C code using cython3!'
    exit 1
fi
gcc -Os -I /usr/include/python3.9 -o ipchanger ipchanger.c -lpython3.9 -lpthread -lm -lutil -ldl
if [ $? -eq 0 ]; then
    echo 'Static binary compiled!'
else
    echo 'Build failed!'
    exit 1
fi
cp -r ipchanger /usr/bin/
if [ $? -eq 0 ]; then
    echo 'Binary copied to /usr/bin!'
else
    echo 'Unable to copy binary!'
    exit 1
fi
