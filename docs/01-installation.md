Niirrty.Md2Pdf is a tool that generates a PDF File from one or more markdown files.


# Installation

Niirrty.Md2Pdf comes as a PHAR archive and can be called if a usable PHP7.3+ is known at your system.

Simple unpack `md2pdf.zip` to a place of your choice.

If you are done with it you have to make it globally usable…
 
## For unixoid systems

### As symbolic link (symlink)

Create a symbolic link from md2pdf.phar to a folder that is a part of your PATH environment variable.

for example

```bash
sudo ln -s ~/programs/md2pdf/md2pdf.phar /usr/bin/md2pdf
```

### As copy

You can also copy it to a folder that is a part of your PATH environment variable.

```bash
sudo cp ~/programs/md2pdf/md2pdf.phar /usr/bin/md2pdf
```

!!pagebreak!!

### Make it executable

```bash
sudo chmod +x /usr/bin/md2pdf
```

If you now call it from somewhere it should work and show the version of used md2pdf

```bash
md2pdf --version
```

## For Windows systems

[Add the current location to PATH environment variable](https://www.howtogeek.com/118594/how-to-edit-your-system-path-for-easy-command-line-access/)
