---
title: "Encrypted Columns"
description: "Encrypt Doctrine columns with #[Encrypted], using one data key per tenant for tenant-aware entities."
---

The PlatformBundle integrates Symfony's [Key Management](https://symfony.com/doc/8.2/key_management.html)
component so a Doctrine column can be encrypted by adding `#[Encrypted]` to it. On tenant-aware
entities, every tenant gets its own data key, and a value can only be decrypted inside the tenant
that wrote it.

The feature turns on by itself when the packages below are installed. Set `platform.kms.enabled: false`
to turn it off.

## Requirements and installation

- Symfony 8.2 or later. FrameworkBundle 8.2 registers the `KeyManagementBundle` for you.
- `doctrine/orm` ^3 and `doctrine/dbal` ^4.6.
- `ext-sodium` or `ext-openssl`.

```bash
composer require symfony/doctrine-orm-key-management
```

This also installs the DBAL bridge and the Key Management component. The packages are optional: the
platform does not require them.

Create the schema for the data key table afterwards (for example with a Doctrine migration). Data keys
are stored in the `key_management_data_keys` table. See the
[Symfony Doctrine integration](https://symfony.com/doc/8.2/key_management/doctrine.html) for details.

## Encrypting a column

```php
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[ORM\Entity]
class Customer
{
    #[ORM\Column]
    #[Encrypted]
    private string $phone;
}
```

Only new columns are supported. Existing plaintext columns are not migrated.

## Tenant-aware entities

When [multi-tenancy](../multi-tenancy/index.md) is enabled and the entity is tenant-aware, the platform
encrypts the column with a data key for the tenant that owns the row. The scope of that key is
`platform.tenant:<tenant ULID>`: one key per tenant, shared by all of that tenant's encrypted columns.
The tenant is also bound into each stored value, so a value copied to another tenant's row does not
decrypt.

- Tenant-aware entities must use `#[Encrypted]`. Mapping a tenant-aware column directly to an encrypted
  DBAL type throws.
- A key scope shared between a tenant-aware column and a column that is not tenant-aware throws.

### Reading and writing needs a tenant

Encrypted tenant-aware columns can only be read or written while a tenant is in scope, for example
inside `TenantManager::runAs($tenant, ...)` or a request where the tenant context is set. Without one,
the operation throws. This includes `runWithoutFilter()` and any read of another tenant's row.

There is no way around this: no flag, no "system" tenant and no fallback key. To process data for
several tenants, loop over the tenants and use `runAs()` for each.

## Configuration

```yaml
# platform.yaml
platform:
    kms:
        enabled: null                    # null: on when symfony/doctrine-orm-key-management is installed
        key_id: platform                 # the key the platform stores its data keys under
        allow_local_key_in_prod: false   # allow the local key file outside dev and test
```

Set the master key with the `KMS_DSN` environment variable. Without it, the DSN is
`sodium+dir://%kernel.project_dir%/config/kms?ext=.key`, which uses the local key file
`config/kms/platform.key` (32 raw bytes).

Leave `dsn` out of `platform.yaml` unless it is a literal DSN with no secret in it. The `%env()%`
values in `platform.yaml` are resolved when the file is read: an unset variable stops the kernel from
booting, and a set one is written into the compiled container in `var/cache`, credentials included.

The platform also configures `key_management` for you: a client named `platform`, and a data key store
that authenticates stored keys with the `KMS_STORE_SECRET` environment variable (falling back to
`kernel.secret` when it is not set).

The container refuses to build, so tenant data is never encrypted under a shared key, when:

- the feature is enabled but `KeyManagementBundle` is not registered, for example because
  FrameworkBundle is still on 8.1. Require `symfony/framework-bundle` ^8.2, register
  `Symfony\Component\KeyManagement\KeyManagementBundle` in your kernel, or set
  `platform.kms.enabled: false`;
- the feature is enabled but `key_management` has no data key store, or is disabled. The platform
  decorates `key_management.stored_envelope_encrypter`, which only exists with a store;
- multi-tenancy is enabled and Doctrine columns are encrypted without the platform, for example with
  `platform.kms.enabled: false` and a `key_management` configuration of your own. Let the platform
  configure `key_management` instead.

## Local key file

In the `dev` and `test` environments the platform generates the key file on first use. Elsewhere you
create it yourself:

```bash
bin/console platform:kms:generate-key
```

The command refuses to overwrite an existing key. `--force` replaces it, and everything encrypted under
the old key becomes unreadable.

Add the key directory to `.gitignore` in your application:

```
/config/kms/
```

In every environment except `dev` and `test`, the first encrypt or decrypt, and
`platform:kms:rotate-tenant`, fail when the local key file is the master key, unless
`allow_local_key_in_prod: true` is set. A warning is logged when
`KMS_STORE_SECRET` is not set.

## Using a remote KMS

Point `KMS_DSN` at a remote key service such as AWS KMS or HashiCorp Vault
Transit. Each backend is a separate bridge package with its own DSN scheme (Vault Transit uses
`hashicorp-vault-transit://`), and its own options. Install the bridge, then take the DSN format from the
Symfony [clients documentation](https://symfony.com/doc/8.2/key_management/clients.html):

```bash
# .env.local or the production environment
KMS_DSN="<DSN from the bridge documentation>"
```

Apps that override
`key_management.clients.platform` directly bypass the local key guard below; use `KMS_DSN` instead.

## Production checklist

- Use a remote KMS, not the local key file.
- Set `KMS_STORE_SECRET` to a dedicated secret. Otherwise `kernel.secret` is used, and changing it
  breaks every stored data key.
- Back up the `key_management_data_keys` table. Without it, encrypted values cannot be read.
- Keep `config/kms/` out of version control.

### Rotating KMS_STORE_SECRET

Do not just change `KMS_STORE_SECRET`. The platform passes a single secret to the data key store, so
the old one is gone and every stored data key, and every encrypted value, becomes unreadable.

Instead, list the new secret first and the old one after it. The first one signs, all of them verify,
and your list replaces the platform's single secret:

```yaml
# config/packages/key_management.yaml
key_management:
    store:
        secret: ['%env(KMS_STORE_SECRET)%', '%env(KMS_STORE_SECRET_OLD)%']
```

Deploy with `KMS_STORE_SECRET` set to the new secret and `KMS_STORE_SECRET_OLD` to the old one, then
rewrap the stored keys:

```bash
bin/console key-management:rewrap-data-keys
```

Once the rewrap has finished, remove `KMS_STORE_SECRET_OLD` from the list and from the environment.

### What the local key guard covers

The local key guard and the tenant isolation apply to the `key_management.stored_envelope_encrypter`
service. That is what Doctrine columns use, and what the `EnvelopeEncrypterInterface` autowiring alias
points to. Per-client `key_management.envelope_encrypter.<name>` services and raw KMS clients are not
guarded and are not tenant-scoped.

## Key rotation

Data keys rotate automatically once they are older than `max_age` (30 days by default, a setting of the
Symfony data key store). Values written earlier keep decrypting.

To rotate on demand:

```bash
bin/console platform:kms:rotate-tenant 01JABCDEFGHJKMNPQRSTVWXYZ0
bin/console platform:kms:rotate-tenant --all
```

Rotation creates a new data key; it does not re-encrypt rows already written.

## Binding the wrapping context

`bind_wrapping_context` is an optional Symfony setting that ties each wrapped data key to its context
at the KMS. It is off by default. Some backends refuse it: sealed box clients, some Azure algorithms,
and Vault Transit keys that are not derived. Turning it on for existing data requires a rewrap of the
stored keys. See the Symfony [documentation](https://symfony.com/doc/8.2/key_management.html).

## Limitations

- A value can be moved between rows of the same tenant without detection.
- New columns only. Existing plaintext columns are not migrated.
- Encrypted columns cannot be searched or sorted in SQL.
- Symfony's `#[BlindIndexed]` blind indexes are not tenant-scoped: equal values match across tenants.
