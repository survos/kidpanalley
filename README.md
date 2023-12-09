# kidpanalley

## Setup

install csvkit (sudo apt install csvkit)
convert the excel files to csv (in2csv kpa-songs.xlsx > songs.csv)
so we don't need spreadsheet kit.


```bash
git clone git@github.com:survos/kidpanalley.git kpa && cd kpa
git checkout tac
bin/console d:database:create 
bin/console doctrine:schema:update --force --complete
bin/console app:load-data
bin/console grid:index
symfony open:local 
```


Tools for KPA

Load the exists assets (youtube and songs) via

```bash
bin/console app:load-data
```
    
Database Tables

* Videos: from youtube now, eventually from Dropbox too.
* Photos: Eventually from Dropbox
* Schools: residencies
* Songs: 5K from the spreadsheet.  Talented Clementine and Best Friends _could_ be added.
* User: for permissions
