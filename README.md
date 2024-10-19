### Code to refactor

Implemented Single-Responsibility Principle (S of the SOLID object-oriented design (OOD) principles) as much as I can.


#### app/Http/Controllers/BookingController.php

- Removed logics in this BookingController class.
- Connected this class to a Service class (BookingService), instead of a Repository class, and move the logics there.
- Responsibilities:
    - accept params
    - pass the params and/or data to the BookingService class
    - return response


#### app/Repository/BookingRepository.php

- Did not use this class anymore (retained just for tracing) and moved the logics in BookingService class.
- Created Repository classes that handles data queries only.
    - JobRepository - data related to Job
    - UserRepository - data related to User
    - TranslatorRepository - data related to Translator
    - and so on.


#### Helpers

- Created Helper classes that can be reused by other classes for specific functions.
    - ApiHelper - functions related to sending api request to third-party
    - LogHelper - functions related to processing log messages
    - and so on.


#### Services

- Created Service classes that will handle logics.
- Connected to Repositories, other Services, and other classes.
    - BookingService - logics related to booking
    - NotificationService - separated logics related to notifications
    - UserTypeRoleService - separated logics related to user roles

------------------------------------

### Code to write tests (optional)

#### App/Helpers/TeHelper.php - method willExpireAt

- Before writing unit tests for this method, I adjusted the logic to ensure that it can be tested properly.
- Created TeHelperTest class (tests\app\Unit\Helpers) that includes four (4) tests.


#### App/Repository/UserRepository.php - method createOrUpdate

- Did not write tests for this method. In my opinion, this needs to be refactored first to be able to perform tests properly.