<?php

namespace cyneek\yii2\routes\components;

use yii\base\Action;
use yii\base\ActionFilter;
use yii\base\Module;

class RoutesFilter extends ActionFilter
{
    public $rule;

    public $type;

    public function beforeAction($action)
    {
        if (!($this->type === 'before')) {
            return true;
        }

        if (is_callable($this->rule)) {
            $return_data = call_user_func($this->rule);

            if (!is_bool($return_data)) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function afterAction($action, $result)
    {
        if (!($this->type === 'after')) {
            return $result;
        }

        if (is_callable($this->rule)) {
            $return_data = call_user_func($this->rule);

            if (!is_bool($return_data)) {
                return false;
            }

            return $result;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param Action $action
     *
     * @return bool
     */
    protected function isActive($action)
    {
        if ($this->owner instanceof Module) {
            // convert action uniqueId into an ID relative to the module
            $mid = $this->owner->getUniqueId();
            $id = $action->getUniqueId();
            if ($mid !== '' && strpos($id, $mid) === 0) {
                $id = substr($id, strlen($mid) + 1);
            }

            $id = $action->controller->getUniqueId().'/'.$id;
        } else {
            $id = $action->controller->getUniqueId().'/'.$action->id;
        }

        return !in_array($id, $this->except, true) && (empty($this->only) || in_array($id, $this->only, true));
    }
}
