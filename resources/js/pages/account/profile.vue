<script setup>
import AccountNav from '@/components/hms/AccountNav.vue'
import { useProfilePhoto } from '@/composables/useProfilePhoto'
import { applySession } from '@/utils/session'

const ability = useAbility()
const userData = useCookie('userData')
const { photoUrl, refreshPhoto } = useProfilePhoto()
const profile = ref(null)
const saving = ref(false)
const photoSaving = ref(false)
const formError = ref('')
const photoError = ref('')
const photoInput = ref(null)
const form = ref({
  name: '',
  phone: '',
  job_title: '',
  specialty: '',
  license_number: '',
  department_id: null,
  availability: 'available',
  preferences: {
    referrals: true,
    encounters: true,
    laboratory: true,
    pharmacy: true,
    invoices: true,
  },
})

const availabilityOptions = [
  { title: 'Available', value: 'available' },
  { title: 'Busy', value: 'busy' },
  { title: 'Away', value: 'away' },
]

const noticeOptions = [
  { key: 'referrals', title: 'Referral queue', description: 'Pending transfers that need a response' },
  { key: 'encounters', title: 'Assigned encounters', description: 'Open visits assigned to you' },
  { key: 'laboratory', title: 'Laboratory queue', description: 'Orders waiting in diagnostics' },
  { key: 'pharmacy', title: 'Pharmacy queue', description: 'Prescriptions awaiting verification' },
  { key: 'invoices', title: 'Billing queue', description: 'Invoices waiting for payment' },
]

const departments = computed(() => profile.value?.profile?.departments || [])
const canChooseDepartment = computed(() => Boolean(userData.value?.hospitalId) && departments.value.length)

const fill = payload => {
  profile.value = payload
  const details = payload.profile || {}
  const user = payload.userData || {}
  form.value = {
    name: user.fullName || '',
    phone: details.phone || user.phone || '',
    job_title: details.job_title || user.jobTitle || '',
    specialty: details.specialty || user.specialty || '',
    license_number: details.license_number || '',
    department_id: details.department_id || user.departmentId || null,
    availability: details.availability || user.availability || 'available',
    preferences: { ...details.preferences },
  }
}

const load = async () => {
  fill(await $api('/auth/profile'))
}

const persist = payload => {
  applySession(payload, ability)
  fill(payload)
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    persist(await $api('/auth/profile', {
      method: 'PUT',
      body: {
        name: form.value.name,
        phone: form.value.phone || null,
        job_title: form.value.job_title || null,
        specialty: form.value.specialty || null,
        license_number: form.value.license_number || null,
        department_id: canChooseDepartment.value ? form.value.department_id : null,
        availability: form.value.availability,
        preferences: form.value.preferences,
      },
    }))
  })
}

const choosePhoto = () => photoInput.value?.click()

const uploadPhoto = async event => {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file)
    return

  await wrapSave(photoSaving, photoError, async () => {
    const body = new FormData()
    body.append('file', file)
    persist(await $api('/auth/avatar', { method: 'POST', body }))
    await refreshPhoto()
  })
}

const removePhoto = async () => {
  await wrapSave(photoSaving, photoError, async () => {
    persist(await $api('/auth/avatar', { method: 'DELETE' }))
    await refreshPhoto()
  })
}

await withPageLoad(load)
</script>

<template>
  <div class="h-account">
    <HPage
      title="Profile"
      subtitle="Your professional identity across this hospital"
    />

    <AccountNav />

    <HCard>
      <div class="h-account-identity">
        <div class="h-account-photo">
          <HAvatar
            :src="photoUrl"
            :name="userData?.fullName"
            :size="88"
            :status="form.availability"
          />
          <input
            ref="photoInput"
            class="h-sr"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="uploadPhoto"
          >
          <div class="h-actions">
            <HButton
              size="sm"
              :disabled="photoSaving"
              @click="choosePhoto"
            >
              <HIcon
                name="camera"
                :size="14"
              />
              {{ userData?.hasAvatar ? 'Replace photo' : 'Add photo' }}
            </HButton>
            <HButton
              v-if="userData?.hasAvatar"
              variant="ghost"
              size="sm"
              :disabled="photoSaving"
              @click="removePhoto"
            >
              Remove
            </HButton>
          </div>
          <p
            v-if="photoError"
            class="h-alert"
          >
            {{ photoError }}
          </p>
        </div>
        <div class="h-account-who">
          <p class="hms-kicker">
            {{ userData?.hospitalName || 'Network operations' }}
          </p>
          <h2>{{ userData?.fullName }}</h2>
          <p>{{ userData?.jobTitle || userData?.roleName }}</p>
          <p class="h-muted">
            {{ [userData?.departmentName, userData?.roleName].filter(Boolean).join(' · ') }}
          </p>
          <p class="h-muted">
            {{ userData?.email }}
          </p>
        </div>
      </div>
    </HCard>

    <HCard title="Personal information">
      <form
        class="h-form is-wide"
        @submit.prevent="save"
      >
        <div
          v-if="formError"
          class="h-alert"
        >
          {{ formError }}
        </div>
        <div class="h-form-grid">
          <HInput
            v-model="form.name"
            label="Full name"
            required
          />
          <HInput
            v-model="form.phone"
            label="Phone"
            optional
          />
        </div>
        <HInput
          :model-value="userData?.email"
          label="Email"
          disabled
          hint="Change your sign-in email from Account security"
        />
        <div class="h-actions">
          <HButton
            type="submit"
            :disabled="saving"
          >
            Save profile
          </HButton>
        </div>
      </form>
    </HCard>

    <HCard title="Professional details">
      <form
        class="h-form is-wide"
        @submit.prevent="save"
      >
        <div class="h-form-grid">
          <HInput
            v-model="form.job_title"
            label="Job title"
            optional
          />
          <HInput
            :model-value="userData?.roleName"
            label="Role"
            disabled
            hint="Assigned by your hospital administrator"
          />
          <HInput
            v-model="form.specialty"
            label="Specialty"
            optional
          />
          <HInput
            v-model="form.license_number"
            label="License number"
            optional
          />
          <HSelect
            v-if="canChooseDepartment"
            v-model="form.department_id"
            label="Department"
            :items="departments"
            item-title="name"
            item-value="id"
            optional
          />
          <HInput
            v-else
            :model-value="userData?.departmentName || 'Not assigned'"
            label="Department"
            disabled
          />
          <HInput
            :model-value="userData?.hospitalName || 'Network operations'"
            label="Hospital"
            disabled
            hint="Use the hospital switcher when you hold more than one membership"
          />
        </div>
        <div class="h-actions">
          <HButton
            type="submit"
            :disabled="saving"
          >
            Save details
          </HButton>
        </div>
      </form>
    </HCard>

    <HCard title="Availability and notifications">
      <form
        class="h-form is-wide"
        @submit.prevent="save"
      >
        <HRadioGroup
          v-model="form.availability"
          label="Availability"
          :items="availabilityOptions"
        />
        <div class="h-account-prefs">
          <p class="hms-menu-label">
            Attention alerts
          </p>
          <HSwitch
            v-for="option in noticeOptions"
            :key="option.key"
            :model-value="form.preferences[option.key]"
            :label="option.title"
            :description="option.description"
            @update:model-value="form.preferences[option.key] = $event"
          />
        </div>
        <div class="h-actions">
          <HButton
            type="submit"
            :disabled="saving"
          >
            Save preferences
          </HButton>
        </div>
      </form>
    </HCard>
  </div>
</template>
