[![Build Status](https://travis-ci.com/mygento/kkm.svg?branch=v2.3)](https://travis-ci.com/mygento/kkm)
[![Latest Stable Version](https://poser.pugx.org/mygento/module-kkm/v/stable)](https://packagist.org/packages/mygento/module-kkm)
[![Total Downloads](https://poser.pugx.org/mygento/module-kkm/downloads)](https://packagist.org/packages/mygento/module-kkm)

# Модуль интеграции Онлайн касс для Magento 1/2

Модуль разрабатывается для полной поддержки требований 54 ФЗ интернет-магазинами на Magento 1 и 2 для сервисов:
* АТОЛ онлайн. Модуль поддерживает версию сервиса АТОЛ v4 (ФФД 1.05).
* Чеконлайн.

## Функциональность модуля

### Передача данных в Онлайн кассу
* отправляет данные о счете/возврате:
  * автоматически при создании счета (настраивается в конфигурации)
  * автоматически при создании возврата (настраивается в конфигурации)
  * вручную одной из консольных команд (см. ниже)
  * вручную из админки кнопкой на странице Счета или Возврата

### Повторная передача данных в Онлайн кассу (Resell)
(Не путать с чеком коррекции)
* Отменяет предыдущий чек прихода (по Invoice) и отправляет новый.
  * вручную при нажатии кнопки `Resell` в админке
  * консольной командой (см. ниже)
  * другой модуль может триггерить `\Mygento\Kkm\Api\Processor\SendInterface::proceedResellRefund`
  
### Получение данных из АТОЛ
* получает из АТОЛ данные о статусе регистрации счета/возврата
  * автоматически (настраивается в конфигурации). После обработки данных АТОЛ отправляет результат обратно (колбек). По умолчанию URL: http://shop.ru/kkm/frontend/callback
  * крон задачей для проверки статусов
  * вручную из админки кнопкой на странице Счета или Возврата
  * консольной командой `mygento:kkm:update-one {$uuid}` или `mygento:kkm:update-all {$storeId}`

### Получение данных из Чеконлайн
Работа сервиса Чеконлайн построена по синхронному принципу, понятие «статус»
документа в сервисе отсутствует.
Вместо этого используются кэширование ответов. Ключём кэша являются поля Group, RequestId, ClientId, что значит, что если будут отправлены запросы с одинаковыми указанными полями, то сервис ответит данными из кэша.
В кэш помещаются успешные ответы и некоторые ошибки устройства Ккм (см. документацию Чеконлайн)

### Процесс отправки данных в Онлайн кассу
1. На основании сущности Invoice или Creditmemo формируется объект `Mygento\Kkm\Api\Data\RequestInterface`.
    1.1. При асинхронной передаче - объект помещается в очередь (см. Magento Queue Framework)
    1.2. При синхронной передаче - передается классу `Vendor` для отправки

2. Регистрируется попытка отправки данных. Создается сущность `Api\Data\TransactionInterface\TransactionAttemptInterface` со статусом `NEW` (1)

3. Осуществляется передача данных в виде JSON.
    
    3.1. В случае **УСПЕШНОЙ** передачи (один из HTTP статусов `[200, 400, 401]`)
    * создается транзакция - сущность `Magento\Sales\Api\Data\TransactionInterface` в который записываются уникальный идентификатор запроса (UUID - Атол; RequestId - Чеконлайн) и все данные о передаче. В админке это грид Sales -> Transactions.
    * Сущность попытки отправки `TransactionAttemptInterface` получает статус `Sent` (2)
    * Создается комментарий к заказу
    * Транзакция получает в ККМ-статус (kkm_status):
      * Атол - `wait`
      * Чеконлайн - `done`

    3.2. В случае **НЕУСПЕШНОЙ** передачи (статусы отличные от `[200, 400, 401]` (так же `500` для Чеконлайн), отсутствие ответа от сервера, некорректные данные в инвойсе или возврате)
    * Сущность попытки отправки `TransactionAttemptInterface` получает статус `Error` (3)
    * Создается комментарий к заказу с описанием причины ошибки
    * Заказ получает статус "KKM Failed"
    * Если выброшено исключение `VendorBadServerAnswerException` (сервер не отвечает и еще в некоторых случаях) и   включена асинхронная передача - то отправка будет снова помещена в очередь.
    * Если выброшено исключение `VendorNonFatalErrorException` и включена асинхронная передача - то:
      * Атол - выполняется генерация нового external_id и отправка будет снова помещена в очередь.
      * Чеконлайн - Сущность транзакции получает статус `wait` и отправка снова помещается в очередь без генерации нового external_id, т.к. ответ с нефатальной ошибкой не кэшируется. Так же при работе «облачного» сервиса Чеконлайн могут возникать ошибки
        возвращающие HTTP код 500 и структуру, содержащая поля: `FCEError`, `ErrorDescription`, `Device` и `Fatal`. Поле `Fatal` со значением `true` показывает, что повторное выполнение запроса приведёт к
        ошибке. Если поле `Fatal` равно `false` то отправка так же помещается в очередь.

4. Только Атол. Модуль автоматически запрашивает у АТОЛа статус по всем транзакциям с ККМ-статусом `wait`
    
    4.1 Попытки обновления статуса прекращаются, когда транзакция получает статус `done` 
    
    4.2 Максимальное количество попыток настройкой модуля ККМ.

5. В случае **НЕУСПЕШНОЙ** передачи выполняется несколько попыток отправки с увеличивающимися интервалами (например через 1 минуту, 5 минут, 15 минут, 30 минут, 1 час). 
    
    5.1 Настройка интервалов доступна в настройках модуля ККМ.
    
    5.2 Максимальное количество попыток отправки тажке ограничего настройкой модуля ККМ. 
    
    5.3 В случае, когда достигается максимальное количество попыток отправки, счетчик попыток обнуляется и отправка возобновляется через сутки.

### Процесс повторной отправки данных (Resell)
Работает только для тех чеков, которые были отправлены и имеют статус `Done`.

1. На основании Invoice создается чек возврата (refund) и отправляется в Онлайн кассу.
2. Создается новая запись Payment Transaction, дочерняя от предыдущей отправки `sell` по этому инвойсу.
3. Когда статус отправки из п.1 становится `Done` (Для Чеконлайн статус отправки сразу становится `Done` в случае успеха) - формируется и отправляется новый чек прихода (sell).
4. Для нового чека прихода создается новая запись Payment Transaction, дочерняя от транзакции для чека возврата (п.2).
 5. Resell считается завершенным, если новый чек прихода (п.3) получает статус `Done`. Обновление статуса происходит так же как и во всех остальных случаях (Для Чеконлайн обновление статуса не происходит т.к. работа сервиса устроена по синхронному принципу)


### Отчеты
Модуль отправляет отчеты об отправленных данных в Онлайн кассу на емейл (в конфиге). Неуспешные отправки отображаются в этом же письме с доп.деталями. Также этот отчет можно посмотреть в консоли.

* Еженедельный (за прошлую неделю), Ежедневный (за текущий день), Ежедневный (за вчерашний день)
* Верстка письма. Файл `view/adminhtml/templates/email/kkm_report-mjml.mjml` содержит верстку письма. Редактируется с помощью сервиса https://mjml.io/


### Поддержка новых версий сервиса АТОЛ Онлайн
Модуль поддерживал версии сервиса v3 и v4. Если выйдет новая версия, необходимо сделать след.шаги:
1.  создать class RequestForVersionX наследник абстрактного класса Request
2.  релилизовать его JSON представление - метод  jsonSerialize()
3.  добавить создание объекта реквеста в  Mygento\Kkm\Model\Atol\RequestFactory
4.  добавить инфу о новой версии сервиса в сурс модель Mygento\Kkm\Model\Source\ApiVersion

### Использование очередей
* отправка сообщений в Онлайн кассу может осуществляться в двух режимах:
  * синхронный (сразу после сохранения сущности или ручной отправки);
  * асинхронно (через нативный механизм очередей сообщений Magento).
* режим работы настраивается в конфигурации

### Ручная отправка данных
* Отправка данных на странице сущности
* Отправка данных консольной командой с указанием IncrementId сущности

### Логирование сообщений
* Модуль логирует (при включенном режиме Debug в Stores -> Configuration -> Mygento Extensions -> Extensions and Support) все запросы (и ответы).
* Лог запросов доступен на странице конфигурации модуля

## Список Rewrite
нет

## Список событий и плагинов, Описание действий и причины

### События
* **sales_order_invoice_save_commit_after**:
  * отправляет данные по инвойсу после его сохранения.
* **sales_order_creditmemo_save_commit_after**:
  * отправляет данные по возврату после сохранения.

### Плагины
* before плагин `ExtraSalesViewToolbarButtons` на метод `Magento\Backend\Block\Widget\Button\Toolbar::pushButtons` добавляет кнопки Отправки в Онлайн кассу и кнопку проверки статуса на страницу сущности в админке

## Список доступных реализованных API
нет

## Список встроенных тестов, что и как они тестируют
нет

## Cron-процессы
* **kkm_statuses** 
  * Только Атол. Обновление статуса: job обновляет статусы транзакций, у которых статус `wait`. По умолчанию каждую минуту
* **kkm_proceed_scheduled_attempt**
  * выполняет повторные попытки отправки запросов по заданному расписанию (scheduled_at).
* **kkm_report**
  * Отчет: job отправки отчета. Частота конфигурируется в админке на стр. настроек модуля. По умолчанию ежедневно в 00:07 

## Консольные команды
* `mygento:kkm:report` - Отображает отчет. Аргументы: today, yesterday, week
* `mygento:kkm:refund` - Отправляет возврат. Аргументы: IncrementId сущности
* `mygento:kkm:sell` - Отправляет счет. Аргументы: IncrementId сущности
* `mygento:kkm:resell` - Запускает процесс resell. Отправляет refund по текущему чеку. Аргументы: IncrementId сущности. При указании ключа `-f` увеличится external_id.
* `mygento:kkm:update-all` - Только Атол. Запрашивает данные о статусе всех отправок со статусом `wait` для указанного стора. Аргументы: StoreID
* `mygento:kkm:update-one` - Только Атол. Запрашивает данные о статусе указанной отправки. Аргументы: UUID
