# UML — Decorator (estrutural)

Código: `app/Patterns/Decorator/`. Documentação do padrão: [Padrões de projeto — Decorator](../PADROES_DE_PROJETO.md#1-decorator-estrutural).

| Participante GoF | Classe real |
|---|---|
| Component | `MenuItem` (interface) |
| Concrete Component | `BaseMenuItem` |
| Decorator | `MenuItemDecorator` (classe abstrata) |
| Concrete Decorators | `BaconDecorator`, `CheeseDecorator`, `EggDecorator` |
| Auxiliares (não são participantes) | `Extra` (valor), `ExtraCatalog` (leitura de `config/foodorder.php`) |

O decorator **mantém uma referência** ao `MenuItem` embrulhado, injetada no construtor, e não cria nem destrói esse objeto; por isso a relação é de **agregação** (losango vazio), não de composição.

```mermaid
classDiagram
    class MenuItem {
        <<interface>>
        +priceCents() int
        +description() string
    }
    class BaseMenuItem {
        -string name
        -int priceCents
        +priceCents() int
        +description() string
    }
    class MenuItemDecorator {
        <<abstract>>
        +SEPARATOR
        #MenuItem inner
        #Extra extra
        +priceCents() int
        +description() string
    }
    class BaconDecorator {
        +priceCents() int
        +description() string
    }
    class CheeseDecorator {
        +priceCents() int
        +description() string
    }
    class EggDecorator {
        +priceCents() int
        +description() string
    }
    class Extra {
        <<valueObject>>
        +string code
        +string name
        +int priceCents
        +isAllowedFor(string productCode) bool
    }
    class ExtraCatalog {
        +all() array
        +codes() array
        +find(string code) Extra
        +allowedFor(string productCode) array
    }
    class OrderService {
        +create(Product, array extraCodes) Order
    }

    MenuItem <|.. BaseMenuItem
    MenuItem <|.. MenuItemDecorator
    MenuItemDecorator <|-- BaconDecorator
    MenuItemDecorator <|-- CheeseDecorator
    MenuItemDecorator <|-- EggDecorator
    MenuItemDecorator o-- "1" MenuItem : inner
    MenuItemDecorator --> "1" Extra : extra
    ExtraCatalog ..> Extra : cria
    OrderService ..> ExtraCatalog
    OrderService ..> MenuItem : monta a cadeia
```

Voltar: [índice](../README.md)
