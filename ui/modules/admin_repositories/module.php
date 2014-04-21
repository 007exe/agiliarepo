<?php

Page::loadModule('repository');
Page::loadModule('uicore');
Page::loadModule('taskmon');
class Module_admin_repositories extends RepositoryModule {
	public function run() {
		if ($this->blockname==='sidebar') return $this->run_sidebar();
		if (isset($this->page->path[3])) return $this->repository($this->page->path[3]);

		return $this->getList();
	}

	public function run_sidebar() {
		if (isset($this->page->path[3])) return $this->getList();
	}

	public function getList() {
		$repos = Repository::getList();
		$ret = '<h1>Repositories</h1>';
		foreach($repos as $repname) {
			$ret .= '<li><a href="/admin/repositories/' . $repname . '">' . $repname . '</a></li>';
		}


		return $ret;

	}

	public function repository($repname) {
		if (isset($this->page->path[4])) {
			$method_name = 'repository_' . $this->page->path[4];
			if (method_exists($this, $method_name)) return $this->$method_name($repname);
		}
		$ret = '<h1>' . $repname . '</h1>';
		$repository = new Repository($repname);
		$table = [
			'Packages' => $repository->count(),
			'OS versions' => implode(', ', $repository->osversions()),
			'Owner' => $repository->owner(),
			'Read access' => implode(', ', $repository->whoCan('read')),
			'Write access' => implode(', ', $repository->whoCan('write')),
			'Admin access' => implode(', ', $repository->whoCan('admin')),
			'Git remote' => $repository->gitRemote(),
			];


		$ret .= UiCore::table($table);

		// Actions: edit settings, clone, delete
		$ret .= '<li><a href="/admin/repositories/' . $repname . '/edit">Edit settings</a></li>';
		$ret .= '<li><a href="/admin/repositories/' . $repname . '/clone">Clone repository</a></li>';
		$ret .= '<li><a href="/admin/repositories/' . $repname . '/delete">Delete repository</a></li>';
		


		return $ret;
	}

	public function repository_edit($repname) {
		$repository = new Repository($repname);
		if (isset($_POST['__submit_form_id']) && $_POST['__submit_form_id']==='repository_edit') {
			$repository->owner = $_POST['owner'];
			$permissions = ['read' => explode(',', $_POST['can_read']), 'write' => explode(',', $_POST['can_write']), 'admin' => explode(',', $_POST['can_admin'])];
			foreach($permissions as $perm => &$who) {
				foreach($who as &$name) {
					$name = trim($name);
				}
			}
			$repository->setPermissions($permissions);
			$repository->git_remote = $_POST['git_remote'];
			$repository->update();
			$repository = new Repository($repname);
		}
		$fields = [
			'owner' => ['type' => 'text', 'label' => 'Repository owner', 'placeholder' => 'Enter owner username'],
			'can_read' => ['type' => 'text', 'label' => 'Who can read<br /><span class="sublabel">Comma separated list of users and groups which can read this repository. Groups should be marked with @ at beginning. A special group @everyone means all users</span>', 'placeholder' => 'Who can read?'],
			'can_write' => ['type' => 'text', 'label' => 'Who can write<br /><span class="sublabel">Comma separated list of users and groups which can make changes to this repository (add/edit/delete packages). Groups should be marked with @ at beginning. A special group @everyone means all users</span>', 'placeholder' => 'Who can write?'],
			'can_admin' => ['type' => 'text', 'label' => 'Who can admin<br /><span class="sublabel">Comma separated list of users and groups which can administer this repository (change settings, delete repository). Groups should be marked with @ at beginning. A special group @everyone means all users</span>', 'placeholder' => 'Who can admin?'],
			'git_remote' => ['type' => 'text', 'label' => 'Git remote URL', 'placeholder' => 'Git remote URL'],
			];

		$values = [
			'owner' => $repository->owner(),
			'can_read' => implode(', ', $repository->whoCan('read')),
			'can_write' => implode(', ', $repository->whoCan('write')),
			'can_admin' => implode(', ', $repository->whoCan('admin')),
			'git_remote' => $repository->gitRemote()
			];

		$ret = '<h1>' . $repname . '</h1>';
		$ret .= '<h2>Edit settings</h2>';

		$code = '';
		foreach($fields as $key => $desc) {
			$code .= UiCore::getInput($key, $values[$key], '', $desc);
		}
		$ret .= UiCore::editForm('repository_edit', NULL, $code);

		return $ret;

	}

	public function repository_clone($repname) {
		if (isset($_POST['__submit_form_id'])) {
			if ($_POST['__submit_form_id']==='new_clone') {
				$destname = @$_POST['destname'];
				if (trim($destname)==='') return 'Empty destination name';
				$task_id = AsyncTask::create(Auth::user()->name, 'repository_clone', 'Clone ' . $repname . ' to ' . $destname, ['from' => $repname, 'to' => $destname], ['complete' => 'window.location="/admin/repositories/' . $destname . '";']);

				$ret = '<h1>Cloning repository ' . $repname . ' to ' . $destname . '</h1>';
				$ret .= TaskMonitor::taskProgress($task_id);
				return $ret;
			}
			
		}
		$ret = '<h1>Create a repository clone</h1>';
		$ret .= '<h2>Source: ' . $repname . '</h2>';

		$fields = [
			'destname' => ['type' => 'text', 'label' => 'Enter new repository name', 'placeholder' => 'New repository name'],
			];
	
		$code = '';
		foreach($fields as $key => $desc) {
			$code .= UiCore::getInput($key, '', '', $desc);
		}

		$ret .= UiCore::editForm('new_clone', NULL, $code, '<input type="submit" value="Create clone" />');
		return $ret;
	}

	public function repository_delete($repname) {
		$repository = new Repository($repname);
		$user = Auth::user();
		if ($repository->owner()!==$user->name) return 'Only repository owner can delete it';

		if (isset($_POST['__submit_form_id'])) {
			if ($_POST['__submit_form_id']==='repository_delete') {
				$repname_confirm = @$_POST['repname_confirm'];
				if ($repname!==$repname_confirm) return 'Confirmation error';
				$task_id = AsyncTask::create(Auth::user()->name, 'repository_delete', 'Deleting ' . $repname, ['repname' => $repname], ['complete' => 'window.location="/admin/repositories";']);

				$ret = '<h1>Deleting repository ' . $repname . '</h1>';
				$ret .= TaskMonitor::taskProgress($task_id);
				return $ret;
			}
			
		}

		$ret = '<h1>Delete repository</h1>';
		$ret .= '<h2>Repository to be deleted: ' . $repname . '</h2>';

		$fields = [
			'repname_confirm' => ['type' => 'text', 'label' => 'Enter repository name to confirm<br /><b>WARNING: this cannot be undone!</b>', 'placeholder' => 'Confirm repository name'],
			];
	
		$code = '';
		foreach($fields as $key => $desc) {
			$code .= UiCore::getInput($key, '', '', $desc);
		}

		$ret .= UiCore::editForm('repository_delete', NULL, $code, '<input type="submit" value="Confirm repository delete" />');
		return $ret;



	}
}
