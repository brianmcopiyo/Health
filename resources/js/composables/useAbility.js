import { ability } from '@/plugins/ability'

export const useAbility = () => ability

export const canNavigate = to => {
  const action = to.meta?.action
  const subject = to.meta?.subject

  if (!action || !subject)
    return true

  return ability.can(action, subject)
}
