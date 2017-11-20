# Build the PDF output

Finally you should call

```bash
md2pdf build
```

to generate/build the PDF output file.

If you want more output info you can use the `--verbose` or `-v` option

```bash
md2pdf build --verbose
```

If you want to read configuration data from a different config json file you can define the file name by

```bash
md2pdf build --config-file=my-md2pdf-config.json
```