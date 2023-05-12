# kidpanalley


```bash
git clone git@github.com:survos/kidpanalley.git kpa && cd kpa

composer install && yarn install --force && yarn dev
bin/console d:database:create 
bin/console doctrine:schema:update --force --complete
symfony proxy:domain:attach kpa
symfony server:start -d
bin/console app:load-data
bin/consume
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
