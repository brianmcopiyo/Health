export const errorCatalog = {
  401: {
    label: 'Session ended',
    title: 'Your clinical session has ended',
    copy: 'Sign in again to return to live capacity, referrals, and the hospital you were working in.',
    hint: 'Notes that were not saved were not sent. Open Caregrid and continue from the last saved record.',
    icon: 'lock',
    action: 'login',
    tone: 'session',
    artTitle: 'A fresh sign-in keeps the ward secure.',
    artCopy: 'Sessions expire so only the people on duty can open patient charts, beds, and referrals.',
    next: 'Sign in to Caregrid',
  },
  403: {
    label: 'Access denied',
    title: 'This part of the hospital is closed to your role',
    copy: 'Your account can open some Caregrid modules, but not this one. A hospital administrator can change that if the work is yours.',
    hint: 'If you reached this page from a shared link, go back to reception or your usual list and continue from there.',
    icon: 'shield',
    tone: 'access',
    artTitle: 'Some corridors stay restricted.',
    artCopy: 'Roles keep theatres, billing, and administration in the hands of the teams that need them.',
    next: 'Open Caregrid',
  },
  404: {
    label: 'Not found',
    title: 'This page is not on the hospital map',
    copy: 'The address may have changed, or the patient, bed, or referral is no longer in the live register.',
    hint: 'Return to reception, or open the last list you were using. If you followed a link, ask the sender for a current record.',
    icon: 'search',
    tone: 'missing',
    artTitle: 'The ward you asked for is not on this map.',
    artCopy: 'Caregrid only opens pages that still exist in the live hospital register.',
    next: 'Open Caregrid',
  },
  408: {
    label: 'Timed out',
    title: 'The hospital request timed out',
    copy: 'Caregrid took too long to finish this action. The record may still be safe; try again in a moment.',
    hint: 'Refresh once. If the ward board is busy, wait a few seconds before sending the same request again.',
    icon: 'clock',
    tone: 'wait',
    artTitle: 'The round did not finish in time.',
    artCopy: 'When the hospital network is slow, Caregrid stops waiting rather than leaving you on a blank screen.',
    next: 'Open Caregrid',
  },
  410: {
    label: 'No longer available',
    title: 'This record has left the live register',
    copy: 'It may have been archived, transferred to another hospital, or removed after discharge.',
    hint: 'Search for the patient from reception, or open referrals if the case moved with them.',
    icon: 'x',
    tone: 'missing',
    artTitle: 'This chart is no longer on the live board.',
    artCopy: 'Archived and transferred records leave the current hospital map so teams work from what is current.',
    next: 'Open Caregrid',
  },
  422: {
    label: 'Not accepted',
    title: 'This clinical request could not be saved',
    copy: 'Some of the information sent was not accepted. Go back, check the fields, and send it again.',
    hint: 'Required dates, identifiers, and selected wards must be complete before Caregrid can store the record.',
    icon: 'x',
    tone: 'invalid',
    artTitle: 'The form did not pass the ward desk.',
    artCopy: 'Caregrid rejects incomplete or conflicting details so the live register stays trustworthy.',
    next: 'Open Caregrid',
  },
  419: {
    label: 'Page expired',
    title: 'This Caregrid page has expired',
    copy: 'For safety, the page stopped accepting this action. Sign in again and repeat it from a fresh screen.',
    hint: 'Open sign in, then return to the patient or bed you were updating. Do not resubmit from a stale tab.',
    icon: 'clock',
    action: 'login',
    tone: 'session',
    artTitle: 'This stamp is no longer valid.',
    artCopy: 'Expired pages protect charts when a session sits idle at a shared hospital desk.',
    next: 'Sign in to Caregrid',
  },
  429: {
    label: 'Slow down',
    title: 'Caregrid paused this action',
    copy: 'Too many requests reached the hospital service in a short time. Wait a moment, then retry once.',
    hint: 'Give the board a few seconds. Repeating the same click will keep the pause in place.',
    icon: 'clock',
    tone: 'limit',
    artTitle: 'The service is protecting the hospital board.',
    artCopy: 'Short pauses keep reception, wards, and diagnostics available when many people work at once.',
    next: 'Open Caregrid',
  },
  500: {
    label: 'Server error',
    title: 'Caregrid hit an unexpected fault',
    copy: 'The clinical service failed on our side. The last complete save is still in the register.',
    hint: 'Refresh shortly. If a patient update is urgent, try the same record again or continue from the last list.',
    icon: 'server',
    tone: 'fault',
    artTitle: 'The clinical service needs a moment.',
    artCopy: 'When something fails inside Caregrid, the honest next step is to wait and try the record again.',
    next: 'Open Caregrid',
  },
  502: {
    label: 'Gateway error',
    title: 'A connecting hospital service did not answer',
    copy: 'Caregrid reached a gateway that did not respond. Refresh to try the hospital network again.',
    hint: 'If the problem continues, use another Caregrid module you can open, then return to this record.',
    icon: 'refresh',
    tone: 'fault',
    artTitle: 'A connecting service went quiet.',
    artCopy: 'Labs, imaging, and related services sometimes miss a beat. A refresh asks them again.',
    next: 'Open Caregrid',
  },
  503: {
    label: 'Unavailable',
    title: 'Caregrid is briefly unavailable',
    copy: 'Maintenance or high load is in progress. Clinical teams can retry in a few minutes.',
    hint: 'Keep this tab, or return from sign in when the hospital service is back. Urgent bedside work should use the last known paper fallback your hospital uses.',
    icon: 'hospital',
    tone: 'fault',
    artTitle: 'The hospital system is standing by.',
    artCopy: 'Short outages keep the register consistent while Caregrid is updated or relieved of load.',
    next: 'Open Caregrid',
  },
  504: {
    label: 'Gateway timeout',
    title: 'The hospital network timed out',
    copy: 'A connected service did not finish in time. Refresh to continue from the last saved state.',
    hint: 'Wait a few seconds before refreshing so the same request is not stacked.',
    icon: 'clock',
    tone: 'wait',
    artTitle: 'A connected service did not finish.',
    artCopy: 'Caregrid stops waiting so you are not left staring at an unfinished clinical screen.',
    next: 'Open Caregrid',
  },
}

export const httpStatus = error => Number(error?.status || error?.response?.status || 0)

export const resolveError = code => {
  const key = Number(code)
  return errorCatalog[key] || errorCatalog[404]
}

export const pageErrorCode = status => {
  const code = Number(status)
  if (!code || code === 401)
    return null
  if (errorCatalog[code])
    return code
  if (code >= 500)
    return 500

  return null
}
