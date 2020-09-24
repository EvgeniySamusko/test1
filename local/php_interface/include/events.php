<?php

define('CONTENT_GROUP_ID', 5);
use Bitrix\Main\EventManager;

//перед добавление проверим что в группе может быть только 10

EventManager::getInstance()->addEventHandler(
    'main',
    'OnBeforeUserUpdate',
    [
        'UserEvents',
        'OnBeforeUserAddOrUpdate',
    ]
);
EventManager::getInstance()->addEventHandler(
    'main',
    'OnBeforeUserAdd',
    [
        'UserEvents',
        'OnBeforeUserAddOrUpdate',
    ]
);

//после добавления/изменения группы отправим уведомление
EventManager::getInstance()->addEventHandler(
    'main',
    'OnAfterUserUpdate',
    [
        'UserEvents',
        'OnAfterUserAddOrUpdate',
    ]
);
EventManager::getInstance()->addEventHandler(
    'main',
    'OnAfterUserAdd',
    [
        'UserEvents',
        'OnAfterUserAddOrUpdate',
    ]
);
class UserEvents
{
    public function OnBeforeUserAddOrUpdate(&$arFields)
    {
        $currentGroups = [];
        foreach ($arFields['GROUP_ID'] as $group) { //получим в удобный вид ID групп данного пользователя
            $currentGroups[] = $group['GROUP_ID'];
        }
        if (in_array(CONTENT_GROUP_ID, $currentGroups)) {//если в группе контент менеджера
            $maxCount = 10;
            if ($arFields['ID'] > 0) {
                //значит обновляем текущего, его в расчетах не учитываем
                $filter = [
                    'GROUP_ID' => CONTENT_GROUP_ID,
                    'USER.ACTIVE' => 'Y',
                    '!USER.ID' => $arFields['ID'],
                ];
            } else { //иначе берем всех
                $filter = [
                    'GROUP_ID' => CONTENT_GROUP_ID,
                    'USER.ACTIVE' => 'Y',
                ];
            }
            $result = \Bitrix\Main\UserGroupTable::getList([
                'filter' => $filter,
                'select' => ['EMAIL' => 'USER.EMAIL'],
            ]);
            $currentUsersInGroupCount = $result->getSelectedRowsCount();
            if ($currentUsersInGroupCount >= $maxCount) {
                global $APPLICATION;
                $APPLICATION->throwException('Превышено максимальное число пользователей - '.$maxCount);

                return false;
            }
            if ($arFields['ID'] > 0) {
                //если обновляем текущего, то запишем ему в доп.свойство текущие группы
                $arGroups = CUser::GetUserGroup($arFields['ID']);
                $arFields['UF_CURRENT_GROUPS'] = $arGroups;
            }
        }
    }

    public function OnAfterUserAddOrUpdate(&$arFields)
    {
        $eventNameEmail = 'NOTIFY_OTHER_USERS';
        $currentGroups = [];
        foreach ($arFields['GROUP_ID'] as $group) { //получим в удобный вид ID групп данного пользователя
            $currentGroups[] = $group['GROUP_ID'];
        }
        if ($arFields['RESULT'] > 0) { //если все прошло успешно
            if (in_array(CONTENT_GROUP_ID, $currentGroups) && (!in_array(CONTENT_GROUP_ID, $arFields['UF_CURRENT_GROUPS']) || empty($arFields['UF_CURRENT_GROUPS']))) {
                //если контент-менеджер и ранее таких групп у него не было то отправим
                $result = \Bitrix\Main\UserGroupTable::getList([
                    'filter' => ['GROUP_ID' => CONTENT_GROUP_ID, 'USER.ACTIVE' => 'Y', '!USER.ID' => $arFields['ID']],
                    'select' => ['EMAIL' => 'USER.EMAIL'],
                ]);
                while ($arUser = $result->fetch()) {
                    $emailsToSend[] = $arUser['EMAIL'];
                }
                if ($emailsToSend) { //отправляем одно письма всем, если надо каждому свою то через цикл
                    \Bitrix\Main\Mail\Event::sendImmediate([
                        'EVENT_NAME' => $eventNameEmail,
                        'LID' => SITE_ID,
                        'C_FIELDS' => [
                            'ID' => $arFields['ID'],
                            'EMAIL' => implode(', ', $emailsToSend),
                        ],
                    ]);
                }
            }
        }
    }
}
