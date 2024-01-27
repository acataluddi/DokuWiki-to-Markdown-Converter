

# Dokumd - DokuWiki to Markdown Converter



## Introduction

A tool written initially by [SilverStripe](https://github.com/mattclegg/silverstripe-doc-restructuring) with subsequent forks (see *Credits* below), which has been quickly refactored and improved a bit to migrate a legacy [Dokuwiki](https://www.dokuwiki.org/dokuwiki) wiki to [Outline](https://www.getoutline.com/).



## Usage

The script goes through all the Dokuwiki documents in the *source* folder one by one, even the ones nested inside other folders. It changes them into Markdown format and puts them neatly in the *destination* folder.

```bash
dokumd source/ destination/
```

Where:

- `source/` is the folder containing the Dokuwiki documents.
- `destination/` is the folder that will contain the converted Markdown files.



## Credits

This project was forked from the *SilverStripe documentation restructuring project*:

- Original developer: [Matt Clegg](https://github.com/mattclegg).
- Forked by [Ludo](https://github.com/ludoza).
- Forked by [Peter Krejci](https://github.com/peterkrejci).
- Forked by [Adriano Cataluddi](https://github.com/acataluddi).