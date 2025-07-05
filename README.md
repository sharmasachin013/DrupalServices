# DrupalServices
ddev drush sql-dump --gzip --result-file=site.sql
ddev drush site:install --account-name=admin --account-pass=admin -y
ddev launch 
db update
