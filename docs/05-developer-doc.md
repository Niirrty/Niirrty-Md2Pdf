# Developer Documentation

If you want develop for Md2Pdf Project, you have to solve one dependency.

To build the required phar project output `composer` must be installed and usable.
 
A tool is required to build the PHAR archive. This tool is provided by packagist.org as a composer package.
 
You can install is by calling
 
```bash
composer global require kherge/box
```

register user depending .composer/vendor/bin in PATH. Open

```bash
nano ~/.bashrc
```

and insert this line at the end (Linux)

```bash
export PATH="~/.composer/vendor/bin:$PATH"
```

now trigger the changes 

```bash
source ~/.bashrc
```

after it check if box is installed successful by calling

```bash
box --version
```

it should shows something like

```
Box (repo)
```

now all is fine :-)

If you are done with all changes, you have to call

```bash
box build -v
```

inside the project root folder. That builds the output `bin/md2pdf.phar` file

If you want to change the box configuration, edit the `box.json` file.

The usable options are [shown here](https://github.com/box-project/box2/blob/2.0/box.json.dist)


## Documentation build

Md2Pdf uses it self to generate the documentation. If you change the documentation call

```bash
md2pdf build -v
```

inside the docs folder.