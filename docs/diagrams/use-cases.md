# Casos de uso

O único ator é o **Operador** (não há autenticação). Mermaid não possui diagrama de casos de uso UML nativo; este diagrama usa a notação de fluxo com elipses (casos de uso) e setas tracejadas rotuladas `«include»`/`«extend»`. Foi revisado manualmente quanto à semântica UML.

- `«extend»` em *Selecionar complementos*: é opcional e só ocorre ao criar o pedido.
- `«include»` em *Consultar detalhe*: a página de detalhe sempre exibe histórico e notificações.
- *Pagamento* e *cancelamento* só estão disponíveis em pedidos `pending`; preparo, finalização e entrega seguem o estado atual (ver [diagrama de estados](order-state.md)).

```mermaid
flowchart LR
    OP([Operador])

    subgraph FoodOrder
        UC1((Consultar cardápio))
        UC2((Criar pedido personalizado))
        UC3((Selecionar complementos))
        UC4((Realizar pagamento simulado))
        UC5((Iniciar preparo))
        UC6((Finalizar preparo))
        UC7((Entregar pedido))
        UC8((Cancelar pedido pendente))
        UC9((Consultar pedidos))
        UC10((Consultar detalhe))
        UC11((Consultar histórico))
        UC12((Consultar notificações))
    end

    OP --- UC1
    OP --- UC2
    OP --- UC4
    OP --- UC5
    OP --- UC6
    OP --- UC7
    OP --- UC8
    OP --- UC9
    OP --- UC10

    UC3 -. "«extend»" .-> UC2
    UC10 -. "«include»" .-> UC11
    UC10 -. "«include»" .-> UC12
```

Mapeamento para rotas: ver [Requisitos e regras de negócio](../REQUISITOS_E_REGRAS_DE_NEGOCIO.md) e [Arquitetura](../ARQUITETURA.md).

Voltar: [índice](../README.md)
