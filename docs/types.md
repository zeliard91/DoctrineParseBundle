# Field Types

DoctrineParseBundle provides several built-in types for mapping PHP values to Parse Server data types. Each type handles automatic conversion between PHP and Parse Server representations.

## Available Types

### boolean

Maps PHP boolean values to Parse Server boolean type.

**Example:**
```php
#[ORM\Field(type: 'boolean')]
private $isPublished;
```

**Parse Server representation:** Boolean
**PHP type:** `bool`

---

### integer

Maps PHP integer values to Parse Server number type.

**Example:**
```php
#[ORM\Field(type: 'integer')]
private $viewCount;
```

**Parse Server representation:** Number
**PHP type:** `int`

---

### float

Maps PHP float values to Parse Server number type.

**Example:**
```php
#[ORM\Field(type: 'float')]
private $price;
```

**Parse Server representation:** Number
**PHP type:** `float`

---

### string

Maps PHP string values to Parse Server string type.

**Example:**
```php
#[ORM\Field(type: 'string')]
private $title;
```

**Parse Server representation:** String
**PHP type:** `string`

---

### encrypted_string

Maps PHP string values to encrypted Parse Server strings. Data is automatically encrypted when saving to Parse Server and decrypted when loading. Uses AES-256-CBC encryption with HMAC SHA-256 authentication.

**Example:**
```php
#[ORM\Field(type: 'encrypted_string')]
private $email;

#[ORM\Field(type: 'encrypted_string')]
private $ssn;
```

**Parse Server representation:** String (base64-encoded encrypted data)
**PHP type:** `string`

**Configuration:**

The encryption service uses environment variables or falls back to `kernel.secret`:

```env
# .env (optional)
APP_ENCRYPTION_KEY=your_32_bytes_encryption_key_here
APP_HMAC_KEY=your_32_bytes_hmac_key_here
```

You can also configure the encoding format in `config/packages/doctrine_parse.yaml`:

```yaml
parameters:
    doctrine.parse.encryption_encoding: 'base64'  # Options: 'base64', 'base64url', 'raw'
```

**⚠️ Important notes:**
- Encrypted fields cannot be queried or filtered in Parse Server (data is opaque)
- Changing encryption keys requires re-encrypting all existing data
- Use this for sensitive data like emails, SSN, credit cards, etc.

---

### date

Maps PHP `DateTime` objects to Parse Server date type.

**Example:**
```php
#[ORM\Field(type: 'date')]
private $publishedAt;
```

**Parse Server representation:** Date
**PHP type:** `\DateTime`

---

### array

Maps PHP arrays to Parse Server arrays. Best for simple collections without key-value structure.

**Example:**
```php
#[ORM\Field(type: 'array')]
private $tags;
```

**Parse Server representation:** Array
**PHP type:** `array` (indexed array)

**Example value:** `['tag1', 'tag2', 'tag3']`

---

### hash

Maps PHP associative arrays to Parse Server objects. Best for key-value data structures.

**Example:**
```php
#[ORM\Field(type: 'hash')]
private $metadata;
```

**Parse Server representation:** Object
**PHP type:** `array` (associative array)

**Example value:** `['key1' => 'value1', 'key2' => 'value2']`

---

### file

Stores file content in Parse Server as a Parse File. Automatically handles file uploads and downloads.

**Example:**
```php
#[ORM\Field(type: 'file')]
private $avatar;
```

**Parse Server representation:** File
**PHP type:** `\Parse\ParseFile`

**Usage:**
```php
use Parse\ParseFile;

$file = ParseFile::createFromFile('/path/to/avatar.jpg', 'avatar.jpg');
$user->setAvatar($file);
$om->persist($user);
$om->flush();
```

---

### object

Stores nested object data in Parse Server. Useful for embedded documents that don't need to be separate Parse objects.

**Example:**
```php
#[ORM\Field(type: 'object')]
private $address;
```

**Parse Server representation:** Object
**PHP type:** `object` or `array`

---

### geopoint

Maps geographic coordinates to Parse Server GeoPoint type. Used for location-based queries.

**Example:**
```php
#[ORM\Field(type: 'geopoint')]
private $location;
```

**Parse Server representation:** GeoPoint
**PHP type:** `\Parse\ParseGeoPoint`

**Usage:**
```php
use Parse\ParseGeoPoint;

$location = new ParseGeoPoint(48.8566, 2.3522); // Paris coordinates
$venue->setLocation($location);
```

---

## Custom Types

You can create custom types by extending the `Redking\ParseBundle\Types\Type` class:

```php
namespace App\Types;

use Redking\ParseBundle\Types\Type;

class MyCustomType extends Type
{
    public function convertToDatabaseValue($value)
    {
        // Convert PHP value to Parse Server value
        return $value;
    }

    public function convertToPHPValue($value)
    {
        // Convert Parse Server value to PHP value
        return $value;
    }
}
```

Then register it in your configuration:

```php
use Redking\ParseBundle\Types\Type;
use App\Types\MyCustomType;

Type::addType('my_custom', MyCustomType::class);
```

---

## Type Conversion

Types automatically handle conversion between PHP and Parse Server representations:

- **`convertToDatabaseValue()`**: Converts PHP value to Parse Server format when persisting
- **`convertToPHPValue()`**: Converts Parse Server value to PHP format when loading

Most types pass values through unchanged, but some types like `date`, `file`, `geopoint`, and `encrypted_string` perform actual conversions.
