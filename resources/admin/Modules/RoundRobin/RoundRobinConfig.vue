<template>
  <div v-loading="loading" class="fss_support">
    <el-row :gutter="20">
      <el-col :sm="24">
        <!-- Round-Robin Status Toggle Card -->
        <div class="el-card mb-4">
          <div class="el-card__header">
            <div class="card-header-title">
              <span class="title">{{ $t('Round-Robin Status') }}</span>
            </div>
          </div>
          <div class="el-card__body">
            <div class="status-toggle-container">
              <el-switch
                  v-model="isRoundRobinActive"
                  :disabled="changingStatus"
                  active-color="#13ce66"
                  active-text="Active"
                  inactive-color="#ff4949"
                  inactive-text="Inactive"
                  @change="setRoundRobinStatus"
              >
              </el-switch>
              <div class="status-description">
                <p v-if="isRoundRobinActive">
                  {{ $t('Round-Robin is active. Your emails will be distributed across multiple connections.') }}</p>
                <p v-else>{{
                    $t('Round-Robin is inactive. Your emails will be sent using the default connection.')
                  }}</p>
              </div>
            </div>
          </div>
        </div>
      </el-col>

      <!-- Round-Robin Configuration (only visible when active) -->
      <el-col v-if="isRoundRobinActive" :sm="24">
        <!-- Current Status Card -->
        <div class="el-card mb-4">
          <div class="el-card__header">
            <div class="card-header-title">
              <span class="title">{{ $t('Current Status') }}</span>
            </div>
          </div>
          <div v-if="stats" class="el-card__body">
            <div v-if="stats.active_connections < 2" class="mb-3">
              <el-alert
                  :closable="false"
                  show-icon
                  title="Round-Robin requires at least 2 active connections"
                  type="warning">
              </el-alert>
            </div>

            <div v-else class="status-container">
              <div class="status-card">
                <div class="status-icon">
                  <i class="el-icon-message"></i>
                </div>
                <div v-if="stats.current_connection" class="status-details">
                  <div class="connection-title">
                    {{ stats.current_connection.title }}
                    <span class="email-count">({{ $t('Email') }} {{ stats.email_count }} of 2)</span>
                  </div>
                  <div class="connection-email">{{ stats.current_connection.sender_email }}</div>
                  <div class="connection-provider">{{ $t('Provider') }}:
                    {{ capitalizeFirst(stats.current_connection.provider) }}
                  </div>
                  <el-progress
                      :color="progressColor"
                      :percentage="calculateEmailProgress(stats.email_count)"
                      :show-text="false"
                      class="mt-2 email-progress">
                  </el-progress>
                </div>
              </div>

              <div class="action-buttons">
                <el-button :loading="resetting" type="primary" @click="resetRoundRobin">
                  {{ $t('Reset Round-Robin') }}
                </el-button>
                <el-button :loading="resettingCounts" type="warning" @click="resetDailyCounts">
                  {{ $t('Reset Today\'s Counts') }}
                </el-button>
              </div>
            </div>
          </div>
        </div>

        <!-- Daily Sending Limits Card -->
        <div class="el-card mb-4">
          <div class="el-card__header">
            <div class="card-header-title">
              <span class="title">{{ $t('Daily Sending Limits') }}</span>
            </div>
          </div>
          <div v-if="stats" class="el-card__body">
            <p class="section-description">
              {{ $t('Set maximum emails per day for each connection. Leave as 0 for unlimited.') }}</p>

            <el-form ref="limitsForm" :model="limitsForm" label-position="top">
              <el-table :data="stats.connection_stats" style="width: 100%">
                <el-table-column :label="$t('Connection')" prop="title">
                  <template slot-scope="scope">
                    <span v-if="isCurrentConnection(scope.row.id)" class="current-indicator">
                      <i class="el-icon-arrow-right"></i>
                    </span>
                    {{ scope.row.title }}
                  </template>
                </el-table-column>
                <el-table-column :label="$t('Daily Limit')" prop="daily_limit" width="200">
                  <template slot-scope="scope">
                    <el-form-item :prop="'limits.' + scope.row.id" class="mb-0">
                      <el-input-number
                          v-model="limitsForm.limits[scope.row.id]"
                          :min="0"
                          :placeholder="$t('Unlimited')"
                          :step="10"
                          size="small">
                      </el-input-number>
                    </el-form-item>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('Today\'s Usage')" prop="today_count" width="200">
                  <template slot-scope="scope">
                    <span :class="{'limit-reached': scope.row.limit_reached}">
                      {{ scope.row.today_count }}
                      <span v-if="scope.row.daily_limit > 0">/ {{ scope.row.daily_limit }}</span>
                      <span v-else>/ ∞</span>
                    </span>
                    <el-progress
                        v-if="scope.row.daily_limit > 0"
                        :percentage="calculateDailyProgress(scope.row.today_count, scope.row.daily_limit)"
                        :show-text="false"
                        :status="scope.row.limit_reached ? 'exception' : ''"
                        class="mt-1 daily-progress">
                    </el-progress>
                  </template>
                </el-table-column>
              </el-table>

              <div class="text-right mt-3">
                <el-button :loading="savingLimits" type="success" @click="saveLimits">
                  {{ $t('Save Limits') }}
                </el-button>
              </div>
            </el-form>
          </div>
        </div>

        <!-- Connection Statistics Card -->
        <div class="el-card mb-4">
          <div class="el-card__header">
            <div class="card-header-title">
              <span class="title">{{ $t('Connection Statistics') }}</span>
            </div>
          </div>
          <div v-if="stats" class="el-card__body">
            <el-table :data="stats.connection_stats" style="width: 100%">
              <el-table-column :label="$t('Connection')" prop="title">
                <template slot-scope="scope">
                  <span v-if="isCurrentConnection(scope.row.id)" class="current-indicator">
                    <i class="el-icon-arrow-right"></i>
                  </span>
                  {{ scope.row.title }}
                </template>
              </el-table-column>
              <el-table-column :label="$t('Sender Email')" prop="sender_email"></el-table-column>
              <el-table-column :label="$t('Provider')" prop="provider">
                <template slot-scope="scope">
                  {{ capitalizeFirst(scope.row.provider) }}
                </template>
              </el-table-column>
              <el-table-column :label="$t('Total Emails Sent')" prop="total_sent">
                <template slot-scope="scope">
                  <el-badge :value="scope.row.total_sent" type="success"/>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </div>

        <!-- How Round-Robin Works Card -->
        <div class="el-card mb-4">
          <div class="el-card__header">
            <div class="card-header-title">
              <span class="title">{{ $t('How Round-Robin Works') }}</span>
            </div>
          </div>
          <div class="el-card__body">
            <p>{{
                $t('The Round-Robin system sends 2 emails from each connection before moving to the next one. If a connection reaches its daily sending limit, it will be skipped until the next day.')
              }}</p>
            <p>{{
                $t('This helps distribute your emails across multiple providers to improve deliverability and avoid sending limits.')
              }}</p>
          </div>
        </div>
      </el-col>

      <!-- Inactive Round-Robin Message -->
      <el-col v-else :sm="24">
        <div class="el-card mb-4">
          <div class="el-card__body">
            <div class="inactive-message">
              <i class="el-icon-info-circle"></i>
              <p>{{
                  $t('Round-Robin is currently disabled. Enable it above to distribute your emails across multiple connections.')
                }}</p>
            </div>
          </div>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script type="text/babel">
export default {
  name: 'RoundRobinConfigRoot',
  components: {},
  data() {
    return {
      stats: null,
      loading: false,
      resetting: false,
      resettingCounts: false,
      savingLimits: false,
      changingStatus: false,
      limitsForm: {
        limits: {}
      },
      isRoundRobinActive: false
    }
  },
  computed: {
    progressColor() {
      return (this.stats && this.stats.email_count === 1) ? '#409EFF' : '#67C23A';
    }
  },
  methods: {
    getStats() {
      this.loading = true;

      this.$get('round-robin/stats')
          .then((response) => {
            if (response && typeof response === 'object') {
              this.stats = response.data;
            }

            // Set the active status (convert to boolean)
            this.isRoundRobinActive = this.stats && this.stats.is_round_robin_active === true;

            // Initialize limits form data
            this.limitsForm.limits = {};
            if (this.stats && this.stats.connection_stats) {
              this.stats.connection_stats.forEach(connection => {
                this.limitsForm.limits[connection.id] = connection.daily_limit;
              });
            }

            this.loading = false;
          })
          .catch((errors) => {
            console.log(errors);
            this.$notify.error({
              title: 'Error',
              message: 'Failed to load round-robin statistics'
            });
            this.loading = false;
          });
    },

    resetRoundRobin() {
      this.resetting = true;

      this.$post('round-robin/reset')
          .then((response) => {
            this.$notify.success({
              title: 'Success',
              message: response.message || 'Round-robin status has been reset'
            });
            this.getStats();
            this.resetting = false;
          })
          .catch((errors) => {
            console.log(errors);
            this.$notify.error({
              title: 'Error',
              message: 'Failed to reset round-robin status'
            });
            this.resetting = false;
          });
    },

    resetDailyCounts() {
      this.resettingCounts = true;

      this.$post('round-robin/reset_daily')
          .then((response) => {
            this.$notify.success({
              title: 'Success',
              message: response.message || 'Daily counts have been reset'
            });
            this.getStats();
            this.resettingCounts = false;
          })
          .catch((errors) => {
            console.log(errors);
            this.$notify.error({
              title: 'Error',
              message: 'Failed to reset daily counts'
            });
            this.resettingCounts = false;
          });
    },

    saveLimits() {
      this.savingLimits = true;

      this.$post('round-robin/save_limits', {
        limits: this.limitsForm.limits
      })
          .then((response) => {
            this.$notify.success({
              title: 'Success',
              message: response.message || 'Daily limits have been saved'
            });
            this.getStats();
            this.savingLimits = false;
          })
          .catch((errors) => {
            console.log(errors);
            this.$notify.error({
              title: 'Error',
              message: 'Failed to save daily limits'
            });
            this.savingLimits = false;
          });
    },

    setRoundRobinStatus(status) {
      this.changingStatus = true;

      this.$post('round-robin/change_status', {
        status: status
      })
          .then((response) => {
            this.$notify.success({
              title: 'Success',
              message: status
                  ? 'Round-Robin has been activated'
                  : 'Round-Robin has been deactivated'
            });

            // If we deactivated, we don't need to fetch stats
            if (status) {
              this.getStats();
            }

            this.changingStatus = false;
          })
          .catch((errors) => {
            console.log(errors);
            this.$notify.error({
              title: 'Error',
              message: 'Failed to change Round-Robin status'
            });

            // Revert the switch back to its previous state
            this.isRoundRobinActive = !status;
            this.changingStatus = false;
          });
    },

    isCurrentConnection(id) {
      return this.stats &&
          this.stats.current_connection &&
          this.stats.current_connection.id === id;
    },

    capitalizeFirst(str) {
      if (!str) return '';
      return str.charAt(0).toUpperCase() + str.slice(1);
    },

    calculateEmailProgress(emailCount) {
      // Convert to number and ensure it's between 0-100
      emailCount = parseInt(emailCount || 0);
      return emailCount === 1 ? 50 : (emailCount >= 2 ? 100 : 0);
    },

    calculateDailyProgress(currentCount, dailyLimit) {
      // Convert to numbers
      currentCount = parseInt(currentCount || 0);
      dailyLimit = parseInt(dailyLimit || 1);

      if (dailyLimit <= 0) return 0;

      // Calculate percentage and ensure it's between 0-100
      const percentage = (currentCount / dailyLimit) * 100;
      return Math.min(100, Math.max(0, percentage));
    }
  },
  mounted() {
    this.getStats();
  }
}
</script>

<style scoped>
.mb-4 {
  margin-bottom: 20px;
}

.mt-1 {
  margin-top: 5px;
}

.mt-2 {
  margin-top: 10px;
}

.mt-3 {
  margin-top: 15px;
}

.mb-0 {
  margin-bottom: 0;
}

.mb-3 {
  margin-bottom: 15px;
}

.text-right {
  text-align: right;
}

.card-header-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header-title .title {
  font-size: 16px;
  font-weight: 600;
}

.status-toggle-container {
  display: flex;
  align-items: center;
}

.status-description {
  margin-left: 20px;
  color: #606266;
}

.status-container {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.status-card {
  display: flex;
  background: #f5f7fa;
  border-radius: 4px;
  padding: 15px;
  flex: 1;
  margin-right: 20px;
}

.status-icon {
  font-size: 32px;
  margin-right: 15px;
  color: #409EFF;
  display: flex;
  align-items: center;
}

.connection-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 5px;
}

.email-count {
  font-size: 13px;
  color: #606266;
  font-weight: normal;
  margin-left: 5px;
}

.connection-email, .connection-provider {
  color: #606266;
  margin-bottom: 5px;
}

.action-buttons {
  display: flex;
  flex-direction: column;
}

.action-buttons .el-button {
  margin-bottom: 10px;
}

.section-description {
  color: #606266;
  margin-bottom: 15px;
}

.current-indicator {
  color: #409EFF;
  margin-right: 5px;
}

.limit-reached {
  color: #F56C6C;
  font-weight: bold;
}

.email-progress, .daily-progress {
  height: 6px;
  width: 100%;
}

.inactive-message {
  display: flex;
  align-items: center;
  color: #606266;
  padding: 20px;
  background: #f5f7fa;
  border-radius: 4px;
}

.inactive-message i {
  font-size: 24px;
  margin-right: 15px;
  color: #e6a23c;
}

/* Fix for Element UI progress bar */
.email-progress :deep(.el-progress-bar__outer),
.daily-progress :deep(.el-progress-bar__outer) {
  background-color: #EBEEF5;
  overflow: hidden;
  position: relative;
  vertical-align: middle;
  border-radius: 100px;
}

.email-progress :deep(.el-progress-bar__inner),
.daily-progress :deep(.el-progress-bar__inner) {
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  background-color: #409EFF;
  text-align: right;
  border-radius: 100px;
  line-height: 1;
  white-space: nowrap;
  transition: width .6s ease;
}

@media (max-width: 768px) {
  .status-toggle-container {
    flex-direction: column;
    align-items: flex-start;
  }

  .status-description {
    margin-left: 0;
    margin-top: 10px;
  }

  .status-container {
    flex-direction: column;
  }

  .status-card {
    margin-right: 0;
    margin-bottom: 15px;
  }

  .action-buttons {
    flex-direction: row;
  }

  .action-buttons .el-button {
    margin-right: 10px;
    margin-bottom: 0;
  }
}
</style>