`Lang: pt-BR`

# ArrayDB PHP - Mysql

Uma abstração para simplificar a ligação entre suas entidades de Orientação a Objeto com seu banco de dados.

## Como usar

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
    new \ArrayDB\Database\Mysql("host", "username", "password", "schema", "charset")
);
```

Ou seja, você não está preso à configuração padrão.

As implementações seguintes foram desenvolvidas para que possa se comunicar usando apenas array's.
> NOTA: Você deve manter em mente que a sintaxe ainda está sendo desenvolvida e mudanças podem ocorrer ao longo do projeto. Por tanto, se instalar esse repositório via Composer, lembre de especificar a versão que irá utilizar corretamente (seja pela identificação do commit ou pelos futuros releases).

### SELECT

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

#### Sintaxe de Operações

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
#### Operadores
No exemplo acima, temos `&` como uma identificação coringa, mas na realidade ele é um exemplo da sintaxe de operadores desse projeto.

Tudo é definido nos índices do array. Por exemplo, o coringa `&` não precisa ser escrito dessa forma. Poderia ser qualquer coisa, desde que tenha o `&` no índice e o valor seja um array com índices do tipo `string`.
Poderia ser `&_1`, `&_2`, `...`, afinal, não podemos repetir índices no array e você pode precisar fazer várias combinações no mesmo nível com o mesmo coringa `&`, não é mesmo?

Da mesma forma, os operadores são atrelados diretamente no nome da variável que está buscando e cada um funciona de uma forma diferente. Podemos separar, portanto, os operadores em duas definições: **operadores de valor** e **operador de agregação**.

##### Operadores de valor
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

##### Operadores de agregação
Esse são os operadores que infuenciam um conjunto de dados e esperam um array de dados para tratar.

| Operador | Ação | Exemplo |
| -------- | ---- | --------- |
| !: | NotIn | isso NOT IN (...) |
| : | In | isso IN (...) |

##### Aplicando operadores
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

#### Ordenação e limitação
No caso das buscas, se quiser definir ordenação ou limitação, use as palavras-chaves `orderBy` e `limit` como condições da busca:
```php
$result = $db->setConditions([
    "nome" => "Kaique",
    "orderBy" => "sobrenome DESC",
    "limit" => "0,10",
])->select();
// SELECT * FROM table_name WHERE nome='Kaique' ORDER BY sobrenome DESC LIMIT 0,10
```

#### Conjuntos (join)
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

### INSERT / UPDATE

`TO-DO`

### DELETE

`TO-DO`

## Pendências do projeto

| S | Descrição |
| ------ | ----------- |
| ✏ | README: finalizar explicações sobre projeto |
| ✏ | Recurso: DELETE + JOIN |
| ❌ | Recurso: TRUNCATE |
| ❌ | Colocar projeto no Packagist.org |
| ❌ | Preparar guia de instalação via composer |
| ❌ | Recurso: CREATE (table) |
| ❌ | Recurso: DROP (table) |

## Recursos disponíveis:

- SELECT
- SELECT + JOIN
- SAVE (INSERT OR UPDATE ON DUPLICATE KEY)
- DELETE
- PRIMARY KEY DISCOVERING
- SAFE DELETE