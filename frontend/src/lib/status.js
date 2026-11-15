/** Visual tone per status. Labels always come from the API. */
export const STATUS_TONE = {
  new: '',
  assigned: '',
  in_progress: 'signal',
  waiting_parts: 'wait',
  completed: 'done',
  cancelled: 'late',
}

export const OPEN_STATUSES = ['new', 'assigned', 'in_progress', 'waiting_parts']
