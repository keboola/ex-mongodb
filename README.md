# MongoDB Extractor

Keboola Connection component for extracting data from MongoDB databases. The extractor uses the `mongoexport` command to export data from specified collections and transforms the output into structured CSV files using field mapping.

**Table of Contents:**

- [Configuration](#configuration)
  - [Connection Parameters](#connection-parameters)
  - [SSH Tunnel](#ssh-tunnel)
  - [SSL/TLS](#ssltls)
  - [Export Options](#export-options)
- [Connection Protocols](#connection-protocols)
  - [mongodb://](#mongodb)
  - [mongodb+srv://](#mongodbsrv)
  - [Custom URI](#custom-uri)
- [Configuration Example](#configuration-example)
- [Output](#output)
- [Development](#development)
- [Integration](#integration)
- [License](#license)

## Configuration

The configuration `config.json` contains the following properties in the `parameters` key.

### Connection Parameters

The `db` object (required) configures the database connection:

- `protocol` - string (optional): One of `mongodb` (default), `mongodb+srv`, or `custom_uri`.

**Parameters for `protocol` = `mongodb` or `mongodb+srv`:**

- `host` - string (required): Hostname of MongoDB server. For `mongodb+srv`, use the [DNS Seedlist Connection Format](https://docs.mongodb.com/manual/reference/connection-string/#dns-seedlist-connection-format).
- `port` - string (optional): Server port (default: `27017`).
- `database` - string (required): Database to connect to.
- `authenticationDatabase` - string (optional): [Authentication database](https://docs.mongodb.com/manual/reference/program/mongo/#authentication-options) for the user.
- `user` - string (optional): User with appropriate access rights.
- `#password` - string (optional): Password for the user. Both `user` and `#password` must be specified together, or neither.

**Parameters for `protocol` = `custom_uri`:**

- `uri` - string (required): Complete [MongoDB Connection String](https://docs.mongodb.com/manual/reference/connection-string/), e.g., `mongodb://user@localhost,localhost:27018,localhost:27019/db?replicaSet=test&ssl=true`. The password must not be included in the URI.
- `#password` - string (required): Password for the user specified in the URI.

Note: When using `custom_uri`, the `host`, `port`, `database`, and `authenticationDatabase` parameters must not be defined separately as they are part of the URI. Custom URI cannot be used with SSH tunnel.

### SSH Tunnel

The `ssh` object (optional) configures SSH tunnel connection:

- `enabled` - boolean (required): Enables SSH tunnel.
- `sshHost` - string (required): IP address or hostname of the SSH server.
- `sshPort` - integer (optional): SSH server port (default: `22`).
- `localPort` - integer (optional): SSH tunnel local port in the Docker container (default: `33006`).
- `user` - string (optional): SSH user (defaults to `db.user`).
- `compression` - boolean (optional): Enables SSH tunnel compression (default: `false`).
- `keys` - object (optional): SSH keys for authentication.
  - `public` - string (optional): Public SSH key.
  - `#private` - string (optional): Private SSH key.

### SSL/TLS

The `ssl` object (optional) configures SSL/TLS encryption:

- `enabled` - boolean (required): Enables SSL/TLS connection.
- `ca` - string (optional): CA certificate.
- `cert` - string (optional): Client certificate.
- `#key` - string (optional): Client private key.
- `cipher` - string (optional): SSL cipher to use.
- `verifyServerCert` - boolean (optional): Verify server certificate (default: `true`).

### Export Options

- `quiet` - boolean (optional): Pass `--quiet` to `mongoexport` to hide logs (default: `false`). This can help resolve `Failed: EOF` issues ([more info](https://stackoverflow.com/a/39122219)).
- `exports` - array (required): List of [export configurations](https://help.keboola.com/components/extractors/database/mongodb/#configure-exports).

Each export object supports the following parameters:

- `enabled` - boolean (optional): Enable or disable this export (default: `true`).
- `id` - scalar (required): Internal identifier for the export.
- `name` - string (required): Name of the output CSV file.
- `collection` - string (required): MongoDB collection name.
- `query` - string (optional): JSON query to filter documents. Must use [strict format](https://help.keboola.com/components/extractors/database/mongodb/#strict-format).
- `sort` - string (optional): JSON string specifying document order. Must use [strict format](https://help.keboola.com/components/extractors/database/mongodb/#strict-format).
- `limit` - string (optional): Maximum number of documents to export.
- `incremental` - boolean (optional): Enable [Incremental Loading](https://help.keboola.com/storage/tables/#incremental-loading) (default: `false`).
- `incrementalFetchingColumn` - string (optional): Column name for [Incremental Fetching](https://help.keboola.com/components/extractors/database/#incremental-fetching).
- `mode` - enum (optional): Export mode.
  - `mapping` (default): Export using specified field mapping ([documentation](https://help.keboola.com/components/extractors/database/mongodb/#configure-mapping)).
  - `raw`: Export documents as plain JSON strings ([documentation](https://help.keboola.com/components/extractors/database/mongodb/#raw-export-mode)).
- `mapping` - object (required for `mode` = `mapping`): Field mapping configuration ([documentation](https://help.keboola.com/components/extractors/database/mongodb/#configure-mapping)).
- `includeParentInPK` - boolean (optional): Include parent document hash in sub-document primary keys (default: `false`). When `false`, sub-documents with identical content generate the same PK regardless of parent. When `true`, each sub-document gets a unique PK based on both its content and parent document. This option only applies to `mapping` mode.

## Connection Protocols

### mongodb://

When `protocol` is not defined or set to `mongodb`, the extractor connects to a single MongoDB node.

```json
{
  "parameters": {
    "db": {
      "host": "127.0.0.1",
      "port": 27017,
      "database": "test",
      "user": "username",
      "#password": "password"
    },
    "exports": []
  }
}
```

### mongodb+srv://

When `protocol` is set to `mongodb+srv`, the extractor connects to a MongoDB cluster using [DNS Seedlist Connection Format](https://docs.mongodb.com/manual/reference/connection-string/#dns-seedlist-connection-format).

```json
{
  "parameters": {
    "db": {
      "protocol": "mongodb+srv",
      "host": "mongodb.cluster.local",
      "database": "test",
      "user": "username",
      "#password": "password"
    },
    "exports": []
  }
}
```

### Custom URI

When `protocol` is set to `custom_uri`, the extractor connects using the URI defined in `uri`. The password must be provided separately in `#password`.

```json
{
  "parameters": {
    "db": {
      "protocol": "custom_uri",
      "uri": "mongodb://user@localhost,localhost:27018,localhost:27019/db?replicaSet=test&ssl=true",
      "#password": "password"
    },
    "exports": []
  }
}
```

## Configuration Example

```json
{
  "parameters": {
    "db": {
      "host": "127.0.0.1",
      "port": 27017,
      "database": "test",
      "user": "username",
      "#password": "password",
      "ssh": {
        "enabled": true,
        "sshHost": "mongodb",
        "sshPort": 22,
        "user": "root",
        "keys": {
          "public": "ssh-rsa ...your public key...",
          "#private": "-----BEGIN RSA PRIVATE KEY-----\n...your private key...\n-----END RSA PRIVATE KEY-----\n"
        }
      }
    },
    "exports": [
      {
        "name": "bronx-bakeries-westchester",
        "collection": "restaurants",
        "query": "{borough: \"Bronx\", \"address.street\": \"Westchester Avenue\"}",
        "incremental": true,
        "mapping": {
          "_id.$oid": {
            "type": "column",
            "mapping": {
              "destination": "id",
              "primaryKey": true
            }
          },
          "name": "name",
          "address": {
            "type": "table",
            "destination": "bakeries-coords",
            "parentKey": {
              "destination": "bakeries_id"
            },
            "tableMapping": {
              "coord.0": "w",
              "coord.1": "n",
              "zipcode": {
                "type": "column",
                "mapping": {
                  "destination": "zipcode",
                  "primaryKey": true
                }
              },
              "street": "street"
            }
          }
        }
      }
    ]
  }
}
```

## Output

After successful extraction, the component generates CSV files containing the exported data. The primary output file is named according to the `name` parameter in the export configuration. Additional files are created based on the `destination` parameters in the mapping section for nested data structures.

Each CSV file is accompanied by a manifest file that describes the data schema for downstream Keboola components.

## Development

Clone this repository and initialize the workspace:

```shell
git clone https://github.com/keboola/ex-mongodb.git
cd ex-mongodb
cp .env.dist .env
docker compose build
docker compose run --rm dev composer install --no-scripts
```

For ARM-based systems (Apple Silicon), build the image with:

```shell
DOCKER_DEFAULT_PLATFORM=linux/amd64 docker compose build
```

Run the test suite:

```shell
docker compose run --rm dev composer tests
```

## Integration

For information about deployment and integration with Keboola, refer to the [deployment section of the developer documentation](https://developers.keboola.com/extend/component/deployment/).

## License

MIT licensed, see [LICENSE](./LICENSE) file.
