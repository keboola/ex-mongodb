# MongoDB extractor

> Docker application for exporting data from MongoDB. Basically, it's a simple wrapper of mongoexport command, which exports data from specified database and collection. Then those data are processed by php-csvmap.

# Usage

> TODO

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/mongodb-extractor-v5.git
cd mongodb-extractor-v5
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 
