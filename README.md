`Lang: pt-BR`

# ArrayDB PHP - Mysql
[![MIT License](https://img.shields.io/badge/license-MIT-green)](./LICENSE.txt)
[![PHP Version](https://img.shields.io/badge/php-7.2+-green)]()
[![Release Version](https://img.shields.io/badge/release-apha.0.0.1-red)]()

Uma abstração para simplificar a ligação entre suas entidades de Orientação a Objeto com seu banco de dados.

#### Sumário
[1. Instalação](#1-instalação)
<br/>[1.1. Via Composer](#11-via-composer)
<br/>[1.2. Via download](#12-via-download)
<br/>[2. Como usar](#2-como-usar)
<br/>[2.1. Select](#21-select)
<br/>[2.1.1. Sintaxe de Operações](#211-sintaxe-de-operações)
<br/>[2.1.2. Operadores](#212-operadores)
<br/>[2.1.2.1. Operadores de valor](#2121-operadores-de-valor)
<br/>[2.1.2.2. Operadores de agregação](#2122-operadores-de-agregação)
<br/>[2.1.3. Aplicando operadores](#213-aplicando-operadores)
<br/>[2.1.4. Ordenação e limitação](#214-ordenação-e-limitação)
<br/>[2.1.5. Conjuntos (join)](#215-conjuntos-join)
<br/>[2.2. Insert](#22-insert)
<br/>[2.3. Update](#23-update)
<br/>[2.4. Save (Insert or Update)](#24-save)
<br/>[2.5. Delete](#25-delete)
<br/>[2.5.1. Safe delete (primary key obrigatória nas condições)](#251-safe-delete)
<br/>[2.5.2. Unsafe delete](#252-unsafe-delete)
<br/>[3. Transaction](#3-transaction)
<br/>[3.1. Closure Transaction](#31-closure-transaction)
<br/>[3.2. Manual Transaction](#32-manual-transaction)
<br/>[4. Pendências do projeto](#4-pendências-do-projeto)
<br/>[5. Recursos disponíveis](#5-recursos-disponíveis)

## 1. Instalação

### 1.1. Via Composer 
O projeto será incluído no Packagist.org em breve para disponibilizar a instalação mais fácil via composer. Por ora, você precisa adicionar o repositório:
```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/kaiqueg/arraydb-php-mysql"
    }  
  ],
  "require": {
    "kaiquegarcia/arraydb-php-mysql": "dev-master"  
  }
}
```
> NOTA: Você deve manter em mente que a sintaxe ainda está sendo desenvolvida e mudanças podem ocorrer ao longo do projeto. Por tanto, se instalar esse repositório via Composer, lembre de especificar a versão que irá utilizar corretamente (seja pela identificação do commit ou pelos futuros releases).

### 1.2. Via download
Após baixar o repositório manualmente, lembre de incluir na execução principal do PHP o **registro de autoload** do projeto:
```
require_once "pasta/do/repositorio/src/spl_autload.php";
```

## 2. Como usar

A primeira coisa que vocẽ deve fazer depois de importar o projeto é configurar o acesso padrão do banco de dados.
```php
\ArrayDB\Database\Settings::setConnectionConfig([
    "host" => "localhost",
    "username" => "root",
    "password" => "",
    "schema" => "schema_name",
]);
``` 

O **charset** padrão é **UTF-8**, mas se precisar mudar é só informar o índice "charset" na configuração acima.

Como dito anteriormente, essa configuração será a configuração padrão de conexão, mas você pode ter diferentes conexões em diferentes instâncias do `\ArrayDB\Database\Connector`.

Por exemplo, você tem dois modos de instanciar uma conexão a uma tabela:
```php
$defaultConnection = new \ArrayDB\Database\Connector("table_name");

$diffConnection = new \ArrayDB\Database\Connector(
    "table_name",
    (new \ArrayDB\Database\Mysql())->connect([
        "host" => "localhost",
        "username" => "root",
        "password" => "",
        "schema" => "nomeDoSchema",
        "charset" => "utf-8", //opcional
    ])
);
```

Ou seja, você não está preso à configuração padrão.

As implementações seguintes foram desenvolvidas para que possa se comunicar usando apenas array's.

### 2.1. SELECT

Considere `$db` uma instância da classe `\ArrayDB\Database\Connector`, que estará representando uma conexão a uma tabela do banco de dados.
Quando você tem a intenção de realizar uma busca, você pode querer:
- definir as condições da busca (conditions);
- definir conjuntos (join);
- e definir o que você quer ter de retorno (selectors).

Tudo isso é definido em métodos diferentes. Por exemplo, digamos que você quer obter `nome`, `sobrenome` e `telefone` onde `pais='BRASIL'`:
```php
$resultado = $db
    ->setConditions([
        "pais" => "BRASIL",
    ])->select([
        "nome",
        "sobrenome",
        "telefone"
    ]);
```

Em `setConditions` definimos as condições da busca usando a sintaxe de operadores desenvolvida nesse projeto.

#### 2.1.1. Sintaxe de Operações

Pode parecer complicado no início, mas na prática é bem simples. Como a comunicação é feita em arrays, usamos os índices para determinar as ações durante a decodificação passo-a-passo das condições de busca.

A primeira coisa que é necessário ter compreensão é: a primeira camada do array de condições será unificada por AND's e, as demais, por padrão, OR's.
```php
$db->setConditions([
    "nome" => "Kaique",
    "sobrenome" => "Garcia",
    "email" => "...",
]);
// resultado: WHERE nome='Kaique' AND sobrenome='Garcia' AND email='...'

$db->setConditions([
    "nome" => "Kaique",
    "" => [
        "sobrenome" => "Garcia",
        "email" => "...",
    ],
]);
// resultado: WHERE nome='Kaique' AND (sobrenome='Garcia' OR email='...')
```
Todos os níveis mais abaixo do array serão unificados por OR's, exceto se for uma operação de agregação (explicado mais abaixo) ou se for explicitamente solicitado no índice anterior que a unificação deve ser sequência de AND's, usando a identificação coringa `&` no índice. Por exemplo:
```php
$db->setConditions([
    "name" => "Kaique",
    "" => [
        "sobrenome" => "Garcia",
        "&" => [ // define que o nível abaixo deve usar AND ao invés de OR
            "email" => "...",
            "telefone" => "(..) ...",
        ],
    ],
]);
// resultado = WHERE nome='Kaique' AND (sobrenome='Garcia' OR (email='...' AND telefone='(..) ...'))
```
#### 2.1.2. Operadores
No exemplo acima, temos `&` como uma identificação coringa, mas na realidade ele é um exemplo da sintaxe de operadores desse projeto.

Tudo é definido nos índices do array. Por exemplo, o coringa `&` não precisa ser escrito dessa forma. Poderia ser qualquer coisa, desde que tenha o `&` no índice e o valor seja um array com índices do tipo `string`.
Poderia ser `&_1`, `&_2`, `...`, afinal, não podemos repetir índices no array e você pode precisar fazer várias combinações no mesmo nível com o mesmo coringa `&`, não é mesmo?

Da mesma forma, os operadores são atrelados diretamente no nome da variável que está buscando e cada um funciona de uma forma diferente. Podemos separar, portanto, os operadores em duas definições: **operadores de valor** e **operador de agregação**.

##### 2.1.2.1. Operadores de valor
Esses são os operadores que influenciam em um único dado e esperam somente isso.

| Operador | Ação | Exemplo |
| -------- | ---- | --------- |
| !~ | NotLike | isso NOT LIKE aquilo |
| \>= | HigherOrEqualThan | isso >= aquilo |
| <= | LowerOrEqualThan | isso <= aquilo |
| \> | HigherThan | isso > aquilo |
| < | LowerThan | isso < aquilo |
| ~ | Like | isso LIKE aquilo |
| ! | Diff | isso != daquilo |
| = | Equal | isso = aquilo (operador padrão) |

##### 2.1.2.2. Operadores de agregação
Esse são os operadores que infuenciam um conjunto de dados e esperam um array de dados para tratar.

| Operador | Ação | Exemplo |
| -------- | ---- | --------- |
| !: | NotIn | isso NOT IN (...) |
| : | In | isso IN (...) |

#### 2.1.3. Aplicando operadores
```php
$db->setConditions([
    "!nome" => "Kaique", // nome != 'Kaique'
    ":sobrenome" => ["Garcia", "Sanchez", "Souza"], // sobrenome IN ("Garcia", "Sanchez", "Souza")
    "~telefone" => "(..) ...%", // telefone LIKE '(..) ...%'
    ">=idade" => 10, // idade >= 10
    "<idade" => 30, // idade < 30
]);
// resultado = WHERE nome != 'Kaique' AND sobrenome IN ('Garcia', 'Sanchez', 'Souza') AND telefone LIKE '(..) ...%' AND idade >= 10 AND idade < 30
```

Com isso, as possibilidades de combinações são enormes.

De todo modo, após definir as condições você já pode realizar a busca.
No geral, se você não definir o que está buscando (selectors), a busca usará o coringa `*` do MySQL para retornar todos os dados da tabela:

```php
$result = $db->setConditions(["nome" => "Kaique"])->select();
// SELECT * FROM table_name WHERE nome='Kaique'
```

Entretanto se você popular o array do método `->select($arr)`, ele buscará o que você definir:
```php
$result = $db->setConditions(["nome" => "Kaique"])->select([
    "nome",
    "sobrenome",
    "telefone",
]);
// SELECT nome, sobrenome, telefone FROM table_name WHERE nome='Kaique'
``` 

#### 2.1.4. Ordenação e limitação
No caso das buscas, se quiser definir ordenação ou limitação, use as palavras-chaves `orderBy` e `limit` como condições da busca:
```php
$result = $db->setConditions([
    "nome" => "Kaique",
    "orderBy" => "sobrenome DESC",
    "limit" => "0,10",
])->select();
// SELECT * FROM table_name WHERE nome='Kaique' ORDER BY sobrenome DESC LIMIT 0,10
```

#### 2.1.5. Conjuntos (join)
Ao considerar o cenário de conjuntos (join), é preciso manter em mente que você será obrigado a determinar um apelido (alias) pra cada tabela e usar esses apelidos nas definições de condições e campos buscados (selectors).

Nesse caso, o primeiro passo após instanciar um `\ArrayDB\Database\Connector` é definir seu apelido (alias):
```php
$db->setAlias("tableA");
```
Agora você já está pronto para definir as tabelas que pretende incluir no conjunto:
```php
$db->join([
    "table" => "another_table",
    "alias" => "tableB",
    "conditions" => [
        "tableB.tableA_id" => "tableA.id",
        ":tableB.`status`" => [1,2,3], // note que usa a mesma sintaxe de operadores
    ],
    "direction" => \ArrayDB\Database\Connector::LEFT_JOIN,
]);
```

Como o array esperado pelo método requer muitos índices, preparei um 'atalho' para gerar esse array caso esqueça quais campos você pode preencher:
```php
$joinSettings = \ArrayDB\Database\Connector::generateJoinSettings(
    "another_table",
    "alias",
    [
        "tableB.tableA_id" => "tableA.id"
    ],
    \ArrayDB\Database\Connector::LEFT_JOIN
);
$db->join($joinSettings);
```

Você pode usar quantos join's quiser:
```php
$settings1 = $settings2 = $settings3 = []; // considere que configurou e essa linha não existe :p
$db->join($settings1)->join($settings2)->join($settings3);
```

Quanto a direção dos conjuntos, você pode escolher uma das constantes de JOIN definidas na classe Connector (requer conhecimento de banco de dados). Por padrão é definido o **LEFT_JOIN**.
```php
\ArrayDB\Database\Connector::LEFT_JOIN;
\ArrayDB\Database\Connector::INNER_JOIN;
\ArrayDB\Database\Connector::OUTER_JOIN;
\ArrayDB\Database\Connector::RIGHT_JOIN;
\ArrayDB\Database\Connector::JOIN; // apenas JOIN
```

Feito isso, os demais métodos para realizar a busca são iguais, apenas lembre-se de usar os apelidos (alias) corretamente!

### 2.2. INSERT

O conceito do INSERT aqui é mais simples que a busca, pois não é algo condicionado. Logo, você precisa apenas determinar os campos e os valores. Por exemplo, se eu quiser inserir nome=Kaique e sobrenome=Garcia na tabela:
```php
$db->setFields([
    "nome" => "Kaique",
    "sobrenome" => "Garcia",
])->insert();
```
Simples assim, basta informar todos os campos obrigatórios para o INSERT no seu banco de dados.

### 2.3. UPDATE

No caso de uma atualização explícita (onde não há tentativa de insert), há uma pequena semelhança entre a inserção e a busca, pois o **update** precisa dos campos a alterar e das condições dos elementos que receberão a alteração.
Por exemplo, se eu quiser definir o sobrenome=Sanchez para todos que tiverem o nome=Kaique, eu precisaria fazer:
```php
$db->setFields([
    "sobrenome" => "Sanchez"
])->setConditions([
    "nome" => "Kaique"
])->update();
```
Note que a [sintaxe de operações](#211-sintaxe-de-operações) de busca também funciona, mas serve somente para as condições, não para os valores alterados.

### 2.4. SAVE

O save é um método que tenta inserir um dado e, caso haja conflito de chave primária, força uma alteração.
Você pode ler sobre esse conceito na documentação do MySQL: [INSERT ... ON DUPLICATE KEY Statement](https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html).
Resumindo, se você tem um dado e nele há a chave primária de um elemento pré-existente na tabela, chamar o método save fará que o banco de dados tente inserir o dado e, depois de encontrar o conflito, faça uma atualização.

Comparando ao caso do **update**, esse método é algo bem mais simples. Por depender do conflito, não há a necessidade de determinar **condições de busca**, apenas o campo a inserir/alterar.
Por exemplo, se eu quiser **cadastrar** `nome='Kaique'` e `sobrenome='Garcia'`:
```php
$db->setFields([
    "nome" => "Kaique",
    "sobrenome" => "Garcia",
])->save();
```
E depois, mais tarde, mudar o sobrenome para `Sanchez`, supondo que esse registro está sob `id=6`:
```php
$db->setFields([
    "id" => 6,
    "sobrenome" => "Sanchez"
])->save();
```

O benefício desse método é que a abstração remove a necessidade de enviar todos os campos. Em casos de alteração, você pode enviar só a identificação e os valores alterados.
> Nota: se há o conhecimento explícito de que é uma alteração e não um cadastro, é recomendado usar o método [update](#23-update), pois custa menos ao seu banco de dados.

### 2.5. DELETE

> Nota: Há em nossa [lista de pendências](#3-pendências-do-projeto) um item sobre delete com join, porém não é exatamente uma necessidade para o projeto. Entretanto, talvez algumas pessoas precisem e por isso será desenvolvido em algum momento.

Para excluir um dado é bem simples, basta informar as condições de busca de acorodo com a [sintaxe de operações](#211-sintaxe-de-operações) e os dados que forem encontrados serão excluídos.

Entretanto, pode ser interessante para alguns projetos excluir somente se houver uma chave primária informada nas condições.


### 2.5.1. SAFE DELETE

Ativado por padrão, não precisa fazer muito além de definir as condições e executar:
```php
$db->setConditions([
    "id" => 6,
    "nome" => "Marcos"
])->delete();
```

No caso acima, não vai excluir nada pois o registro `6` dos exemplostem o nome 'Kaique'.

```php
$db->setConditions([
    "nome" => "Kaique",
])->delete();
```
Já no caso acima, um `\ArrayDB\Exceptions\MissingFieldException` será disparado por não ter o PRIMARY KEY `id` definido nas condições.

### 2.5.2. UNSAFE DELETE

Para desabilitar o SAFE DELETE da exclusão, basta informar o valor `false` no primeiro parâmetro do método `delete`. No caso do último exemplo acima, para não termos nenhum `Exception` disparado bastaria desativar:
```php
$db->setConditions([
    "nome" => "Kaique",
])->delete(false);
```
E então todas as linhas do banco onde `nome='Kaique'` fosse satisfatório seriam excluídas.

## 3. TRANSACTION

Você tem duas formas de executar transações:

### 3.1. CLOSURE TRANSACTION

[Closure](https://www.php.net/manual/pt_BR/class.closure.php) são funções anônimas criadas sob demanda. Nesse caso, fazer transações com Closure é uma forma simples de dizer ao banco "execute tudo isso junto, se algo der errado, desfaça tudo" de forma automatizada.
A forma de fazer isso é bem simples:

```php
$db->transact(function() use ($db) {
    $db->setFields(["nome" => "Kaique", "sobrenome" => "Garcia"])->insert();
    $db->setFields(["nome" => "Kaique", "sobrenome" => "Sanchez"])->insert();
    $db->setFields(["nome" => "Kaique", "sobrenome" => "Menezes"])->insert();
});
```

No código acima, se algum dos inserts disparar uma exceção (ou qualquer parte do código no mesmo escopo), todos os inserts serão descartados. Caso contrário, tudo irá passar normalmente.
Se você precisar disparar algo pro banco no meio do Closure, basta chamar a ação de `commit`.

```php
$db->transact(function() use ($db) {
    $db->setFields(["nome" => "Kaique", "sobrenome" => "Garcia"])->insert();
    $db->commit(); // envia o primeiro insert pro banco
    $lastId = $db->getLastInsertedId();
    $db->setFields(["nome" => "Kaique", "sobrenome" => "Sanchez", "parente" => $lastId])->insert();
});
```

No caso acima, usei o ID gerado do primeiro insert no segundo.

### 3.2. MANUAL TRANSACTION

Ainda assim, closures se tornam difíceis de gerenciar quando há muitos parâmetros para jogar dentro. Nesse caso, você pode usar os métodos de transação em seu próprio try-catch:

```php
try {
    $db->beginTransaction();
    $db->setFields(["nome" => "Kaique", "sobrenome" => "Garcia"])->insert();
    $db->setFields(["nome" => "Kaique", "sobrenome" => "Sanchez"])->insert();
    $db->setFields(["nome" => "Kaique", "sobrenome" => "Menezes"])->insert();
    $db->commit();
} catch (\Throwable $throwable) {
    $db->rollback();
    // faça alguma coisa com o erro (sério... um log, talvez)
}
```

## 4. Pendências do projeto

| S | Descrição |
| ------ | ----------- |
| ✏ | Recurso: CREATE TABLE |
| ❌ | Atualizar README |
| ❌ | Recurso: INSERT + SELECT |
| ❌ | Recurso: UPDATE + JOIN |
| ❌ | Colocar projeto no Packagist.org |
| ❌ | Preparar guia de instalação via composer |
| ❌ | Recurso: DELETE + JOIN |
| ❌ | Recurso: TRUNCATE MULTIPLE TABLES |
| ❌ | Recurso: DROP (table) |

## 5. Recursos disponíveis:

- SELECT
- SELECT + JOIN
- INSERT
- UPDATE
- SAVE (INSERT OR UPDATE ON DUPLICATE KEY)
- DELETE
- PRIMARY KEY COLUMN NAME DISCOVER
- SAFE DELETE
- TRUNCATE
- CREATE DATABASE
- JOIN de diferentes SCHEMAS
- TRANSACTION
