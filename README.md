# DrupalServices
chmod +x .devcontainer/setup_project.sh


ddev drush sql-dump --gzip --result-file=../db/site.sql

hooks:
  post-start:
    - exec-host: "ddev import-db --file=db/site.sql.gz  && ddev drush cim -y && ddev drush cr"
    - exec: "echo ✅ DB import After Start......."

  pre-stop:
     - exec-host: "ddev drush cr && ddev drush cex -y && ddev drush sql-dump --gzip --result-file=../db/site.sql"  


