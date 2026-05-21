// install.js - TheAdmin 安装向导脚本

class InstallWizard {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 5;
        this.formData = {};
        this.apiBase = '/api/sys/install';
        this.envChecksPassed = false;
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadStep(1);
        this.checkInstallStatus();
    }

    bindEvents() {
        document.getElementById('btn-next').addEventListener('click', () => this.nextStep());
        document.getElementById('btn-prev').addEventListener('click', () => this.prevStep());
        
        // 步骤点击事件
        document.querySelectorAll('.step').forEach(step => {
            step.addEventListener('click', (e) => {
                const targetStep = parseInt(step.dataset.step);
                if (targetStep < this.currentStep || (targetStep === 2 && this.envChecksPassed)) {
                    this.currentStep = targetStep;
                    this.loadStep(this.currentStep);
                }
            });
        });
    }

    async checkInstallStatus() {
        try {
            const response = await fetch(`${this.apiBase}/status`);
            const result = await response.json();
            
            if (result.code === 20000 && result.data.installed) {
                // 已安装，显示提示
                this.showInstalledNotice();
            }
        } catch (error) {
            console.error('检查安装状态失败:', error);
        }
    }

    showInstalledNotice() {
        const notice = document.createElement('div');
        notice.className = 'security-notice';
        notice.style.marginTop = '20px';
        notice.innerHTML = '<strong>提示：</strong>系统已安装，如需重新安装请先删除 <code>runtime/theadmin_install.lock</code> 文件和 <code>plugin/theadmin/config/database.php</code> 文件。';
        
        const stepContent = document.querySelector('.step-panel[data-step="1"] .welcome-info');
        if (stepContent) {
            stepContent.appendChild(notice);
        }
    }

    loadStep(step) {
        // 更新步骤指示器
        document.querySelectorAll('.step').forEach((el, index) => {
            const stepNum = index + 1;
            el.classList.remove('active', 'completed');
            if (stepNum === step) {
                el.classList.add('active');
            } else if (stepNum < step) {
                el.classList.add('completed');
            }
        });

        // 更新步骤线
        document.querySelectorAll('.step-line').forEach((line, index) => {
            line.classList.toggle('active', index + 1 < step);
        });

        // 显示对应面板
        document.querySelectorAll('.step-panel').forEach(panel => {
            panel.classList.remove('active');
        });
        const targetPanel = document.querySelector(`.step-panel[data-step="${step}"]`);
        if (targetPanel) {
            targetPanel.classList.add('active');
        }

        this.updateButtons();

        // 根据步骤执行特定逻辑
        if (step === 2) {
            this.checkEnvironment();
        }
    }

    updateButtons() {
        const prevBtn = document.getElementById('btn-prev');
        const nextBtn = document.getElementById('btn-next');

        prevBtn.disabled = this.currentStep <= 1;

        if (this.currentStep === this.totalSteps) {
            nextBtn.textContent = '完成';
            nextBtn.onclick = () => window.location.reload();
        } else if (this.currentStep === 4) {
            nextBtn.textContent = '开始安装';
            nextBtn.onclick = () => this.executeInstall();
        } else {
            nextBtn.textContent = '下一步';
            nextBtn.onclick = () => this.nextStep();
        }

        // 第1步隐藏上一步按钮
        prevBtn.style.visibility = this.currentStep === 1 ? 'hidden' : 'visible';
    }

    nextStep() {
        // 第2步需要环境检测通过才能继续
        if (this.currentStep === 2 && !this.envChecksPassed) {
            alert('请等待环境检测完成');
            return;
        }

        // 第3步需要验证表单
        if (this.currentStep === 3) {
            if (!this.validateStep3Form()) {
                return;
            }
        }

        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.loadStep(this.currentStep);
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.loadStep(this.currentStep);
        }
    }

    async checkEnvironment() {
        try {
            const response = await fetch(`${this.apiBase}/environment`);
            const result = await response.json();

            if (result.code === 20000) {
                this.renderEnvironmentChecks(result.data);
                this.envChecksPassed = result.data.all_passed;
            }
        } catch (error) {
            console.error('环境检测失败:', error);
            this.showEnvError('环境检测失败，请刷新页面重试');
        }
    }

    renderEnvironmentChecks(data) {
        const checksContainer = document.getElementById('env-checks');
        const summaryEl = document.getElementById('env-summary');

        if (!checksContainer || !summaryEl) return;

        checksContainer.innerHTML = '';

        Object.entries(data.checks).forEach(([key, check]) => {
            const item = document.createElement('div');
            item.className = `check-item ${check.passed ? 'passed' : 'failed'}`;
            item.innerHTML = `
                <span class="check-name">${check.name}</span>
                <span class="check-value">
                    ${check.passed ? '✓ 通过' : '✗ 未通过'}
                    ${check.current !== undefined && check.current !== true && check.current !== false 
                        ? `（当前: ${check.current}）` : ''}
                </span>
            `;
            checksContainer.appendChild(item);
        });

        summaryEl.style.display = 'flex';
        summaryEl.className = `check-summary ${data.all_passed ? 'success' : 'error'}`;
        
        const iconEl = summaryEl.querySelector('.summary-icon');
        const textEl = summaryEl.querySelector('.summary-text');
        
        if (iconEl) {
            iconEl.textContent = data.all_passed ? '✓' : '✗';
        }
        if (textEl) {
            textEl.textContent = data.all_passed
                ? '环境检测通过，可以继续安装'
                : '环境检测未通过，请修复上述问题后重试';
        }
    }

    showEnvError(message) {
        const summaryEl = document.getElementById('env-summary');
        if (summaryEl) {
            summaryEl.style.display = 'flex';
            summaryEl.className = 'check-summary error';
            const iconEl = summaryEl.querySelector('.summary-icon');
            const textEl = summaryEl.querySelector('.summary-text');
            if (iconEl) iconEl.textContent = '✗';
            if (textEl) textEl.textContent = message;
        }
    }

    validateStep3Form() {
        // 验证数据库表单
        const dbForm = document.getElementById('database-form');
        const dbData = this.serializeForm(dbForm);

        if (!dbData.database) {
            alert('请填写数据库名');
            return false;
        }

        // 验证管理员表单
        const adminForm = document.getElementById('admin-form');
        const adminData = this.serializeForm(adminForm);

        if (!adminData.admin_username) {
            alert('请填写管理员用户名');
            return false;
        }

        if (!adminData.admin_password) {
            alert('请填写管理员密码');
            return false;
        }

        if (adminData.admin_password.length < 6) {
            alert('管理员密码至少6个字符');
            return false;
        }

        if (adminData.admin_password !== adminData.admin_password_confirm) {
            alert('两次输入的密码不一致');
            return false;
        }

        // 保存表单数据
        this.formData = { ...dbData, ...adminData };
        delete this.formData.admin_password_confirm;

        return true;
    }

    async executeInstall() {
        // 显示安装进度
        this.currentStep = 4;
        this.loadStep(4);
        this.updateStepsIndicator();

        // 更新安装进度
        this.updateInstallStep(1, 'active');

        try {
            // 模拟进度更新
            await this.delay(500);
            this.updateInstallStep(1, 'completed');
            this.updateInstallStep(2, 'active');
            document.getElementById('install-status').textContent = '正在创建数据库...';

            await this.delay(500);
            this.updateInstallStep(2, 'completed');
            this.updateInstallStep(3, 'active');
            document.getElementById('install-status').textContent = '正在安装数据表...';

            await this.delay(500);
            this.updateInstallStep(3, 'completed');
            this.updateInstallStep(4, 'active');
            document.getElementById('install-status').textContent = '正在创建管理员...';

            // 执行实际安装
            const response = await fetch(`${this.apiBase}/execute`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(this.formData),
            });

            const result = await response.json();

            await this.delay(500);
            this.updateInstallStep(4, 'completed');
            this.updateInstallStep(5, 'active');
            document.getElementById('install-status').textContent = '安装完成...';

            if (result.code === 20000) {
                // 安装成功
                document.getElementById('admin-username').textContent = this.formData.admin_username;
                document.getElementById('admin-password').textContent = this.formData.admin_password;
                
                await this.delay(500);
                this.updateInstallStep(5, 'completed');
                
                this.currentStep = 5;
                this.loadStep(5);
            } else {
                throw new Error(result.msg || '安装失败');
            }
        } catch (error) {
            alert('安装失败: ' + error.message);
            // 返回上一步
            this.currentStep = 3;
            this.loadStep(3);
        }
    }

    updateInstallStep(step, status) {
        const stepEl = document.querySelector(`.install-step[data-step="${step}"]`);
        if (stepEl) {
            stepEl.classList.remove('pending', 'active', 'completed');
            stepEl.classList.add(status);
            
            const iconEl = stepEl.querySelector('.step-icon');
            if (iconEl) {
                if (status === 'completed') {
                    iconEl.textContent = '✓';
                } else {
                    iconEl.textContent = step;
                }
            }
        }
    }

    updateStepsIndicator() {
        document.querySelectorAll('.step').forEach((el, index) => {
            const stepNum = index + 1;
            el.classList.toggle('active', stepNum === this.currentStep);
            el.classList.toggle('completed', stepNum < this.currentStep);
        });
    }

    serializeForm(form) {
        const data = {};
        if (!form) return data;
        
        const formData = new FormData(form);
        for (let [key, value] of formData.entries()) {
            // 处理复选框
            if (form.querySelector(`[name="${key}"]`)?.type === 'checkbox') {
                data[key] = form.querySelector(`[name="${key}"]`).checked;
            } else {
                data[key] = value;
            }
        }
        return data;
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// 测试数据库连接
async function testDatabaseConnection() {
    const btn = document.getElementById('btn-test-connection');
    const resultEl = document.getElementById('connection-result');
    
    if (!btn || !resultEl) return;
    
    const form = document.getElementById('database-form');
    const data = {};
    
    // 获取表单数据
    const formData = new FormData(form);
    for (let [key, value] of formData.entries()) {
        if (form.querySelector(`[name="${key}"]`)?.type === 'checkbox') {
            data[key] = form.querySelector(`[name="${key}"]`).checked;
        } else {
            data[key] = value;
        }
    }

    btn.disabled = true;
    btn.textContent = '测试中...';
    resultEl.style.display = 'none';

    try {
        const response = await fetch('/api/sys/install/test-connection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        });

        const result = await response.json();

        if (result.code === 20000) {
            resultEl.className = 'connection-result success';
            resultEl.textContent = result.data.message || '连接成功';
        } else {
            resultEl.className = 'connection-result error';
            resultEl.textContent = result.msg || '连接失败';
        }
    } catch (error) {
        resultEl.className = 'connection-result error';
        resultEl.textContent = '连接失败: ' + error.message;
    } finally {
        btn.disabled = false;
        btn.textContent = '测试连接';
    }
}

// 绑定测试连接按钮事件
document.addEventListener('DOMContentLoaded', () => {
    new InstallWizard();
    
    // 等待 DOM 加载完成后绑定测试连接按钮
    setTimeout(() => {
        const testBtn = document.getElementById('btn-test-connection');
        if (testBtn) {
            testBtn.addEventListener('click', testDatabaseConnection);
        }
    }, 100);
});
