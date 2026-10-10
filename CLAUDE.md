# CLAUDE.md - PivotPHP Core Routing

Este arquivo orienta o trabalho no pacote **pivotphp/core-routing**.

## Visão geral

**pivotphp/core-routing** é o motor de roteamento do PivotPHP, extraído do `pivotphp-core`.
Faz **uma coisa bem**: registra rotas, compila padrões e casa o path — sem cache, sem plugins e
sem "otimizações" que não têm efeito real em PHP-FPM (SPEC-086).

API inspirada no Express.js, compatível com PSR-7/PSR-15.

## Recursos

- **API Express.js**: verbos `get/post/put/delete/patch/options/head/any`, `match()` e `add()`.
- **Grupos e prefixos**: `group()` prefixa rotas (com grupos aninhados); `use()` só associa middlewares a um prefixo, sem alterar caminhos (SPEC-053).
- **Compilação de padrões**: `:param`, `{param}` e `<constraint>` viram regex (shortcuts
  `int`, `slug`, `alpha`, `alnum`, `uuid`, `date`, `year`, `month`, `day`).
- **Casar**: `identify($method, $path)` com varredura linear simples.
- **Introspecção**: `getRoutes()`, `getHttpMethodsAccepted()`, `toString()`.
- **Isolamento**: estado por instância (SPEC-076) — cada `Router` tem suas rotas, grupos e middlewares; `clear()` limpa só aquela instância.
- **Arquivos estáticos**: `StaticFileManager` registra arquivos como rotas no router informado (deduplicação por router); ignora arquivos/diretórios ocultos e symlinks que apontem para fora da pasta (SPEC-062).

## Estrutura

```
pivotphp-core-routing/
├── src/
│   ├── Router/                  # Implementação
│   │   ├── Router.php           # Router por instância (registrar + compilar + casar)
│   │   ├── RouterFacade.php     # @deprecated — fachada estática sobre uma instância compartilhada
│   │   ├── RouterInstance.php   # Sub-router de instância usado por group()
│   │   ├── Route.php            # Valor-objeto legado (compatibilidade)
│   │   ├── RouteCollection.php  # Coleção legada (compatibilidade)
│   │   ├── StaticFileManager.php        # Registra cada arquivo do diretório como rota
│   │   └── SimpleStaticFileManager.php  # @deprecated — subclasse de compatibilidade
│   └── Utils/                   # Utilitários
│       ├── CallableResolver.php # Resolve handler (closure | [Classe, 'metodo'])
│       ├── Arr.php
│       └── Utils.php
└── tests/
    ├── Router/
    ├── Unit/
    └── Integration/
```

## Comandos essenciais

```bash
composer test           # PHPUnit
composer phpstan        # PHPStan 2 nível 9 (phpstan.neon)
composer cs:check       # PSR-12 (phpcs.xml)
composer cs:fix         # Auto-correção
composer quality:check  # phpstan + cs:check + test
```

## API do Router

Desde a 3.0.0 os métodos são **de instância** (SPEC-076); só `compilePattern()` e
`isStaticRoute()` continuam estáticos. A `RouterFacade` (`@deprecated`) e `Router::default()`
mantêm uma instância compartilhada para código legado — não use em código novo.

```php
use PivotPHP\Routing\Router\Router;

$router = new Router();
$router->get('/users', fn($req, $res) => $res->json([]));
$router->get('/users/:id<\d+>', [UserController::class, 'show']); // array callable

$router->group('/api', function ($group) {
    $group->get('/status', fn() => 'ok');
}, [$authMiddleware]);

$router->use('/admin', $adminMiddleware); // middlewares para rotas que começam com /admin (não prefixa)

$route = $router->identify('GET', '/users/42'); // ?array
$routes = $router->getRoutes();
$router->clear();
```

### Handler

Formatos suportados: `Closure`, função nomeada, ou array callable
`[Classe::class, 'metodo']`. O formato legado `'Controller@method'` **não** é suportado
(`TypeError`). A resolução é feita por `Utils\CallableResolver`.

## Padrões de código

- `declare(strict_types=1)`, PSR-12, PHPStan nível 9 (tolerância zero).
- Tipos declarados em todos os parâmetros e retornos.
- O tipo do handler é **intencionalmente** `callable|array` (sem value type) para casar com a
  API pública do `pivotphp-core`; o shape do array callable é validado/narrowed dentro de
  `add()` via `CallableResolver`. Da mesma forma, `identify()`/`getRoutes()` retornam arrays
  "soltos" de propósito — não adicionar `@return array<...>` explícito, pois isso vira erro
  "mixed" no consumidor (`pivotphp-core/phpstan.neon` tem `treatPhpDocTypesAsCertain: false`).
  As regras de `ignoreErrors` em `phpstan.neon` documentam essas duas exceções.

## Testes

- `tests/Router/` — comportamento do Router (compilação, identificação, grupos, estáticos).
- `tests/Unit/` — unidades isoladas (parâmetros, callables, regex).
- `tests/Integration/` — integração.

Ao mudar o comportamento, atualize os testes correspondentes. A suíte do `pivotphp-core` também
é consumidora (CI do core garante que `identify()`/`getRoutes()`/verbos continuam funcionando).

## Integração com o PivotPHP Core

O `pivotphp-core` consome este pacote via `PivotPHP\Routing\Router\Router` (namespace). O
`Application` do core cria a sua própria instância (`new Router()`), registra rotas via
`$this->router->get(...)` e passa essa instância ao `StaticFileManager`.

Mudanças na API pública deste pacote podem quebrar o `pivotphp-core` — verifique o uso no
consumidor antes de alterar contratos.

## Versionamento

- **Versão atual**: 3.0.0 (SPEC-076: Router com estado por instância — MAJOR). Na 2.2.x: SPEC-062
  (estáticos sem dotfiles nem symlinks para fora da pasta), SPEC-057 (CI e PHPStan 2) e SPEC-053
  (`use()` não altera mais o caminho das rotas).
- **PHP**: 8.1+
- **Licença**: MIT

## Suporte

- **GitHub**: https://github.com/PivotPHP/pivotphp-core-routing
- **Packagist**: https://packagist.org/packages/pivotphp/core-routing
