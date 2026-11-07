# 10 — Technical Interview Question Bank (50 Questions, Complexity Analyses & Hands-On Exercises)

**Target Candidates:** University Placements, New Grad Software Engineers (Google L3, Microsoft SDE I), Entry-Level Backend Engineers  
**Level Calibration:** Core questions target fundamental computer science concepts expected in new-grad technical rounds. Advanced system design questions are explicitly marked as `[Staff/Advanced Extension]`.  
**Categories:** 10 Core Engineering Disciplines (5 Questions per category = 50 total questions) + Hands-On SQL & PHP Debugging Exercises  
**Standard:** Clear explanations, concrete repository references, computational complexity analyses ($O$), practical coding problems, and analyses of why common weak answers fail.

---

## Category 1: PHP & Modern Backend Engineering

### Q1. Why did you use `declare(strict_types=1);` across the codebase, and what does it actually change?
- **Answer:** By default, PHP coerces scalar arguments (e.g. passing numeric string `'42'` to an `int` parameter succeeds silently). `declare(strict_types=1)` forces the PHP engine to enforce strict typing on function arguments and return types for the file where it is declared. If a string is passed where an integer is expected, PHP throws a `TypeError`. In VerityVote, this guarantees that database IDs, status codes, and timestamps cannot experience silent type coercion bugs.
- **Why Weak Answers Fail:** Weak candidates think `strict_types=1` enables strict mode globally across the whole application or that it turns PHP into a compiled language. It is strictly file-scoped.

### Q2. How does PHP's request lifecycle differ from long-running application runtimes like Node.js or Go?
- **Answer:** PHP typically runs under a **Share-Nothing** architecture (via Apache `mod_php` or PHP-FPM). For every incoming HTTP request, a PHP worker initializes its execution context, allocates memory, runs the script, destroys all variables and memory upon emitting the response, and resets. Node.js and Go maintain a persistent memory space across requests. The share-nothing model eliminates memory leaks across requests and guarantees isolation, but incurs overhead in parsing and bootstrapping on every hit (mitigated by OPcache).
- **Why Weak Answers Fail:** Saying "PHP is single-threaded while Go is multi-threaded" misses the architectural point: PHP-FPM spawns multiple worker processes, but each request starts with a pristine state.

### Q3. What is OPcache, and what role does it play in production performance?
- **Answer:** OPcache is PHP's built-in bytecode cache. Normally, the Zend Engine parses PHP scripts into tokens, generates an Abstract Syntax Tree (AST), and compiles it into opcode before executing. OPcache stores the compiled opcode in shared memory. Subsequent requests execute the cached opcode directly, eliminating file reads, parsing, and compilation overhead, resulting in 2x–4x throughput improvements.

### Q4. What is the difference between `==` and `===` in PHP, and where is this critical in VerityVote?
- **Answer:** `==` performs type coercion before comparison (`0 == 'admin'` evaluates to `true` in older PHP, or `0 == false` evaluates to `true`). `===` checks both value and type identity. In [`AuthService.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Services/AuthService.php#L123), checking user role `in_array($user['role'], ['admin'], true)` passes `true` as the third argument to force strict `===` comparison. Using loose comparison could allow integer 0 or boolean true to bypass role authentication.

### Q5. How does password hashing work in PHP, and why should you never call `md5()` or `sha256()` on a password?
- **Answer:** `password_hash()` implements slow, salted cryptographic key derivation functions (Argon2id and Bcrypt). It automatically generates a cryptographically secure random salt and stores the salt, algorithm identifier, cost factor, and hash in a unified string. Standard SHA-256 or MD5 hashes are designed to be fast (billions of calculations/sec on GPUs), making them trivial to crack via offline rainbow tables and dictionary attacks. Argon2id is intentionally slow and memory-hard.

---

## Category 2: Object-Oriented Programming & Clean Architecture

### Q6. How does VerityVote follow the Single Responsibility Principle (SRP)?
- **Answer:** Each layer has a single reason to change:
  - Repositories ([`VotingRepository.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Repositories/VotingRepository.php)): Solely responsible for executing SQL and managing transactions.
  - Services ([`VotingService.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Services/VotingService.php)): Solely responsible for business logic, receipts, and audit trail dispatching.
  - Controllers/Views ([`View.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Core/View.php)): Solely responsible for rendering HTML views.
- **Why Weak Answers Fail:** Weak answers claim "SRP means a class has only one method." SRP means a class has only one actor or reason to change.

### Q7. What design pattern is used in `Database::getInstance()` and `Cache::getInstance()`, and why?
- **Answer:** The **Singleton Pattern**. It guarantees that a class has only one instance and provides a global access point. In VerityVote, `Database::getInstance()` ensures that a single database connection handle is shared across all repositories throughout a single HTTP request lifecycle, preventing connection exhaustion and ensuring that `beginTransaction()` and `commit()` operate on the exact same physical MySQL socket.

### Q8. How is Dependency Injection implemented without a heavy framework like Laravel or Symfony?
- **Answer:** Via **Constructor Injection with Default Fallbacks**. For example:
  ```php
  public function __construct(?VotingRepository $votingRepo = null) {
      $this->votingRepo = $votingRepo ?? new VotingRepository();
  }
  ```
  In production, instantiating `new VotingService()` automatically defaults to the live repository. In automated tests, developers can inject mock repositories or isolated test instances directly into the constructor.

### Q9. What is the difference between an Interface and an Abstract Class, and how is it used in your Cache system?
- **Answer:** An interface defines a contract with no implementation ([`CacheDriverInterface`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Core/Cache.php#L15)), defining methods `get`, `set`, `has`, `delete`, `clear`. An abstract class can provide partial concrete implementation. VerityVote uses `CacheDriverInterface` so that `FileCacheDriver` and `ArrayCacheDriver` can be swapped interchangeably without altering higher-level code.

### Q10. What is the Open/Closed Principle (OCP), and how does your migration engine illustrate it?
- **Answer:** Software entities should be open for extension, but closed for modification. When adding Migration 006 for composite indexes, no existing migration files (`001`–`005`) or migration runner core code were modified. The system was extended simply by adding a new versioned SQL script to the `database/migrations/` directory.

---

## Category 3: SQL & Relational Database Design

### Q11. What is an Index-Only Covering Scan, and where does VerityVote use one?
- **Answer:** A query where all columns requested in `SELECT`, `WHERE`, `GROUP BY`, and `ORDER BY` are contained entirely within the leaf pages of a secondary B-Tree index. MySQL does not need to visit the clustered index (table rows on disk) at all, indicated by `Extra: Using index` in `EXPLAIN`. VerityVote uses this in Migration 006 (`idx_votes_election_candidate`) to tally election results directly from the index.
- **Why Weak Answers Fail:** Saying "covering index means an index that covers the primary key." All secondary InnoDB indexes automatically include the primary key, but a covering index specifically covers all columns requested by that specific query.

### Q12. What is the difference between Clustered Index and Secondary Index in InnoDB?
- **Answer:** The Clustered Index stores the actual row data in the leaf nodes of the primary key B-Tree. There is only one clustered index per table. Secondary indexes store the indexed column values plus the primary key pointer. When a query uses a secondary index that is not covering, it performs an index lookup followed by a "bookmark lookup" to the clustered index to fetch the remaining columns.

### Q13. Why did you use `SET FOREIGN_KEY_CHECKS = 0;` at the beginning of `schema.sql`?
- **Answer:** To prevent circular or ordering dependency errors when dropping and recreating related tables during schema initialization. When foreign key checks are active, dropping a parent table that is referenced by an existing child table triggers a fatal constraint violation. Disabling checks allows clean, idempotent schema rebuilds. It is re-enabled at the end (`SET FOREIGN_KEY_CHECKS = 1;`).

### Q14. What are the trade-offs of using `ENUM` columns (e.g. `role`, `status`) in MySQL?
- **Answer:** `ENUM` columns store strings internally as compact 1-byte or 2-byte integers, optimizing storage and query performance while enforcing schema-level domain validation. The trade-off is rigidity: altering the set of allowed enum values requires an `ALTER TABLE` statement (though modern MySQL 8.0+ supports instant enum additions if added at the end of the list).

### Q15. What is the difference between `COUNT(*)`, `COUNT(id)`, and `COUNT(column)`?
- **Answer:** `COUNT(*)` counts all rows returned by the query regardless of NULL values. `COUNT(column)` counts only rows where `column` is NOT NULL. In VerityVote's results query:
  ```sql
  LEFT JOIN votes v ON c.id = v.candidate_id
  ```
  We use `COUNT(v.id)` because candidates with zero votes produce a NULL `v.id`, correctly resulting in a count of 0 instead of 1.

---

## Category 4: Operating Systems & System Programming

### Q16. How does file locking (`flock`) work, and what is the difference between `LOCK_SH` and `LOCK_EX`?
- **Answer:** `flock()` operates at the OS kernel level on open file descriptors.
  - `LOCK_SH` (Shared Lock): Allows multiple readers to concurrently read the file while blocking writers.
  - `LOCK_EX` (Exclusive Lock): Grants exclusive write access to exactly one process, blocking all other readers and writers.
  VerityVote uses `LOCK_SH` during cache reads and `LOCK_EX | LOCK_NB` (non-blocking) during cache stampede mutex acquisition.

### Q17. What is a Deadlock, and what are the Coffman conditions required for one to occur?
- **Answer:** A deadlock is a state where two or more processes are permanently blocked because each holds a resource that the other requires. The four Coffman conditions are: (1) Mutual Exclusion, (2) Hold and Wait, (3) No Preemption, and (4) Circular Wait. VerityVote breaks condition (4) Circular Wait by enforcing a strict, identical locking order across all voting transactions.

### Q18. How does `usleep()` work under the hood in a POSIX / Windows operating system?
- **Answer:** `usleep(microseconds)` suspends the execution of the calling thread for the specified duration by invoking the operating system's scheduler (e.g. `nanosleep` in Linux or `SleepEx` in Windows). The thread relinquishes the CPU core to other runnable threads and is placed into a sleep wait queue until the OS hardware timer triggers a wakeup interrupt.

### Q19. What is the difference between Process Concurrency and Thread Concurrency?
- **Answer:** Processes operate in separate virtual address spaces with isolated memory tables managed by the OS MMU; inter-process communication requires IPC (pipes, sockets, shared memory). Threads exist within the same process address space and share heap memory, open file descriptors, and global variables, requiring synchronization primitives (mutexes, semaphores) to prevent memory race conditions.

### Q20. What is memory paging, and what causes a page fault?
- **Answer:** Memory paging divides virtual memory into fixed-size blocks (typically 4 KB) mapped to physical RAM via page tables. A page fault occurs when a thread accesses a virtual memory address that is not currently mapped to physical RAM, requiring the OS kernel to load the page from swap disk or initialize the allocation.

---

## Category 5: Networking & HTTP Protocols

### Q21. Explain the difference between HTTP status codes 401 Unauthorized and 403 Forbidden.
- **Answer:**
  - `401 Unauthorized`: Specifically means "Unauthenticated". The client has not provided valid credentials, and the response must include a `WWW-Authenticate` header challenge.
  - `403 Forbidden`: Means "Authenticated, but Unauthorized". The server knows who the user is, but the user does not have permission to access the requested resource.
  VerityVote returns 401 when visiting `/metrics` without a Bearer token, and 403 when a student attempts to access `/admin/election_create`.

### Q22. What happens during a standard TCP 3-way handshake?
- **Answer:** (1) Client sends `SYN` (Synchronize sequence number $X$), (2) Server responds with `SYN-ACK` (Synchronize sequence number $Y$, Acknowledges $X+1$), (3) Client sends `ACK` (Acknowledges $Y+1$). Once established, application data (HTTP) can flow bi-directionally over the full-duplex socket.

### Q23. What are HTTP Keep-Alive connections, and why are they important for voting throughput?
- **Answer:** In HTTP/1.1, `Connection: keep-alive` allows multiple sequential HTTP requests and responses to be sent over the same underlying TCP connection without tearing down and re-establishing the TCP socket (avoiding the 3-way handshake latency and TLS negotiation on every request).

### Q24. How does the `SameSite` attribute on session cookies mitigate CSRF?
- **Answer:** `SameSite` dictates whether cookies are attached to cross-site requests:
  - `SameSite=Strict`: Cookie is never sent on cross-site requests (even following regular links).
  - `SameSite=Lax`: Cookie is omitted on cross-site subrequests (images, iframes, cross-site POSTs) but sent when a user navigates to the origin site directly.
  - `SameSite=None`: Cookie is sent on all cross-site requests (requires `Secure` flag).
  VerityVote sets `SameSite=Lax` on session cookies.

### Q25. Why did you use `parse_url($requestUri, PHP_URL_PATH)` in `public/index.php`?
- **Answer:** `$_SERVER['REQUEST_URI']` contains the full request string including query parameters (e.g. `/public/login?ref=help`). `parse_url(..., PHP_URL_PATH)` extracts only the clean URL path (`/public/login`), ensuring that routing switch statements match the intended route regardless of arbitrary query parameters appended by clients.

---

## Category 6: Authentication & Application Security

### Q26. What is a Timing Attack, and how does `hash_equals()` prevent it?
- **Answer:** Standard string comparison operators (`==` or `===`) terminate on the first mismatched character ($O(k)$ early return). An attacker measuring response times over thousands of requests can deduce the secret character by character based on microsecond latency variations. `hash_equals()` performs constant-time comparison, taking the exact same execution time regardless of where mismatches occur.

### Q27. What is Session Fixation, and how is it mitigated in VerityVote?
- **Answer:** An attacker sets a known session ID in a victim's browser (e.g. via an unauthenticated link or shared public terminal). When the victim logs in, the attacker uses the known session ID to access the authenticated session. Mitigated in [`Auth.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Core/Auth.php#L86) by calling `Session::regenerate()`, which issues a completely new, cryptographically random session ID upon successful authentication.

### Q28. How does `auth_version` in the `users` table enable instant global session revocation?
- **Answer:** Standard PHP session files on disk do not communicate with the database. When an administrator revokes access or a user resets their password, `auth_version` increments from $N$ to $N+1$ in the database. When any active session submits a request, `Auth::user()` compares the session's cached `auth_version` with the database value; if they differ, it destroys the session and logs the user out immediately.

### Q29. What is the difference between Authentication (AuthN) and Authorization (AuthZ)?
- **Answer:** Authentication verifies *who you are* (verifying credentials against `users` table). Authorization verifies *what you are permitted to do* (checking if role `'student'` is allowed to execute `createElection`).

### Q30. Why is query-string token authentication (`/metrics?token=XYZ`) a security hazard?
- **Answer:** URL query strings are persistently recorded in plain text across multiple untrusted locations: browser history, proxy server logs, web server access logs, and HTTP `Referer` headers passed to external links. Using `Authorization: Bearer` headers keeps secrets in HTTP headers, which are excluded from standard access logs.

---

## Category 7: Concurrency & Transaction Management

### Q31. What are the ACID properties in database systems?
- **Answer:**
  - **Atomicity:** All statements in a transaction succeed, or all are rolled back.
  - **Consistency:** Transactions transition the database from one valid state to another, satisfying all constraints.
  - **Isolation:** Concurrent transactions execute without interfering with one another.
  - **Durability:** Committed changes survive system crashes and power failures.

### Q32. What is the difference between `LOCK IN SHARE MODE` and `FOR UPDATE` in MySQL?
- **Answer:**
  - `LOCK IN SHARE MODE` acquires a Shared Lock (S-lock). Other transactions can read the row and acquire their own shared locks, but no transaction can modify the row until all shared locks release.
  - `FOR UPDATE` acquires an Exclusive Lock (X-lock). Other transactions are blocked from modifying the row or acquiring shared locks; they must wait until the transaction commits or rolls back.

### Q33. What is a "Phantom Read", and how does InnoDB prevent it in `REPEATABLE READ`?
- **Answer:** A phantom read occurs when transaction $T_1$ reads a range of rows matching a condition, transaction $T_2$ inserts a new row matching that condition and commits, and $T_1$ re-executes the query, seeing the newly appeared "phantom" row. InnoDB uses **Next-Key Locking** (combining index record locks with gap locks on preceding index intervals) to block other transactions from inserting rows into the scanned gap.

### Q34. What is the difference between Optimistic Concurrency Control (OCC) and Pessimistic Concurrency Control (PCC)?
- **Answer:**
  - **Pessimistic:** Assumes conflicts will happen; locks records upfront (`FOR UPDATE`) to serialize access (used in VerityVote's voting engine).
  - **Optimistic:** Assumes conflicts are rare; records proceed without locking, checking a version number (`WHERE version = 5`) at commit time; if altered, the transaction aborts and retries.

### Q35. Why must you never perform an external HTTP API call inside an active database transaction?
- **Answer:** Database connections and row locks are held open for the entire duration of the transaction. If an external API call takes 2 seconds (or times out at 30 seconds), all locked database rows remain blocked, starving the database thread pool and causing cascade connection exhaustion across the entire application.

---

## Category 8: Caching & Performance Optimization

### Q36. What is the "Cache Stampede" (Thundering Herd) problem?
- **Answer:** Occurs when a popular cached item expires under high traffic. Hundreds of concurrent requests simultaneously detect a cache miss and query the database to recompute the value, overwhelming the database CPU and I/O. VerityVote prevents this using a non-blocking mutex lock (`flock`), allowing one process to recompute while others serve stale data or wait briefly.

### Q37. What is the difference between Cache-Aside (Read-Aside) and Write-Through caching?
- **Answer:**
  - **Cache-Aside:** The application queries the cache first; on a miss, it queries the database, writes the result to the cache, and returns it. Writes update the database and invalidate the cache.
  - **Write-Through:** The application writes data directly to the cache, which synchronously writes through to the database before acknowledging success.

### Q38. Why does `Cache::guardElectoralSafety()` exist in VerityVote?
- **Answer:** It enforces an architectural invariant: code must never cache voter eligibility, passwords, or cast ballots. If a developer accidentally wrote `$cache->set('voter_eligible_12', true)`, stale cache data could allow an ineligible voter to vote. The guard throws an immediate `RuntimeException`.

### Q39. What is the difference between Latency and Throughput?
- **Answer:** Latency is the time required to complete a single operation (measured in milliseconds). Throughput is the number of operations completed per unit of time (measured in requests/sec or tx/sec). Low latency often enables high throughput, but a system with high concurrency can achieve high throughput even with moderate latency.

### Q40. What is the p95 / p99 percentile, and why is average latency misleading?
- **Answer:** Average latency hides severe outliers (e.g. 99 requests take 2 ms, but 1 request takes 1,000 ms; average is ~12 ms). The 95th percentile (p95) represents the maximum latency experienced by 95% of users. In VerityVote's load tests, p95 reveals the true user experience under peak database lock contention.

---

## Category 9: System Design & Scalability

### Q41. How would you design rate-limiting for a public API?
- **Answer:** Using the **Token Bucket** or **Sliding Window Counter** algorithm backed by Redis. For each incoming request by client IP or API key, an atomic Redis script decrements available tokens. If tokens reach zero, return `HTTP 429 Too Many Requests` with a `Retry-After` header.

### Q42. What is Database Sharding, and what are its primary disadvantages?
- **Answer:** Sharding partitions a database horizontally across multiple physical database servers based on a shard key (e.g. `hash(university_id) % N`).
  - **Disadvantages:** Cross-shard `JOIN` operations become impossible or extremely slow; cross-shard distributed transactions require complex two-phase commit (2PC); resharding data when adding nodes requires expensive rebalancing.

### Q43. What is the CAP Theorem, and where does VerityVote sit?
- **Answer:** In a distributed data store, you can only guarantee at most two out of three: Consistency, Availability, and Partition Tolerance. Because network partitions ($P$) are an inevitable reality of physical networks, systems must choose between Consistency ($CP$) or Availability ($AP$). VerityVote prioritizes **Consistency ($CP$)**: it will reject a ballot with a concurrency conflict rather than accept an inconsistent or duplicate vote under network partitions.

### Q44. How does an API Gateway differ from a standard Reverse Proxy?
- **Answer:** A reverse proxy (e.g. standard Nginx/Apache) routes incoming requests to backend servers and handles SSL termination. An API Gateway adds advanced application-level concerns: authentication token verification, rate limiting, request transformation, dynamic routing, circuit breaking, and telemetry aggregation.

### Q45. What is the difference between Horizontal and Vertical scaling?
- **Answer:** Vertical scaling (scaling up) adds more CPU, RAM, or faster NVMe disks to a single server node (bounded by hardware limits). Horizontal scaling (scaling out) adds more server instances to a pool behind a load balancer, providing theoretical unlimited scalability but requiring stateless application architecture.

---

## Category 10: Git, CI/CD & DevSecOps

### Q46. What is the difference between `git merge` and `git rebase`?
- **Answer:** `git merge` combines two branches by creating a new "merge commit" that ties together the histories, preserving the exact chronological sequence and branch structure. `git rebase` rewrites commit history by moving the base of your feature branch onto the tip of the target branch, producing a clean, linear commit history without merge commits.

### Q47. Why was `actions/checkout@v4` upgraded to `@v7` in Phase 13.3?
- **Answer:** GitHub Actions deprecated the Node.js 20 runner runtime. Actions targeting Node.js 20 triggered platform deprecation warnings and forced runtime compatibility modes. Upgrading to `actions/checkout@v7` natively uses the Node.js 24 runtime, ensuring forward compatibility with upcoming Ubuntu 26 runner platform upgrades.

### Q48. What is the principle of Least Privilege in CI/CD pipelines?
- **Answer:** Granting only the minimum permissions necessary for a workflow to execute. In VerityVote's `.github/workflows/ci.yml`, the workflow explicitly declares:
  ```yaml
  permissions:
    contents: read
  ```
  This prevents a compromised test runner from modifying repository branches or creating rogue releases.

### Q49. What is a "Disposable Test Database" pattern, and why is it superior to mocks?
- **Answer:** The CI runner dynamically provisions a temporary database schema (`verityvote_test_suite`), applies real migrations, runs tests with real SQL execution, and drops the schema at teardown. This tests real database engine behavior (indexes, constraints, deadlocks) while guaranteeing zero test data pollution on production databases.

### Q50. What is the purpose of Git hooks (e.g. pre-commit, pre-push)?
- **Answer:** Custom scripts executed locally before Git operations complete. A `pre-commit` hook can run syntax linters (`lint_all.php`) or whitespace checks (`git diff --check`) to block syntax errors from ever being committed to local history.

---

## 11. Hands-On SQL Interview Coding Exercises

Top-tier technical interviews (Google, Microsoft, Meta) almost always include live SQL query writing. Below are three realistic SQL challenges based directly on VerityVote's schema:

### Exercise 1: Calculate Election Turnout Percentage by Department
- **Problem:** Write a SQL query to compute the total eligible voters, total participated voters, and turnout percentage for each academic department in a specific election (`election_id = 1`).
- **Solution:**
  ```sql
  SELECT 
      u.department,
      COUNT(ee.id) AS total_eligible,
      COUNT(ep.id) AS total_voted,
      ROUND(
          IF(COUNT(ee.id) > 0, (COUNT(ep.id) / COUNT(ee.id)) * 100, 0.0), 
          2
      ) AS turnout_percentage
  FROM election_eligibility ee
  JOIN users u ON ee.user_id = u.id
  LEFT JOIN election_participation ep 
      ON ee.election_id = ep.election_id AND ee.user_id = ep.user_id
  WHERE ee.election_id = 1
  GROUP BY u.department
  ORDER BY turnout_percentage DESC, u.department ASC;
  ```
- **Complexity Analysis:**
  - **Time Complexity:** $O(E \log E + D \log D)$, where $E$ is the number of eligible voters for the election and $D$ is the number of distinct departments. By utilizing the compound unique key `uk_eligibility_election_user`, filtering is $O(\log N)$ on B-Tree index lookup.
  - **Space Complexity:** $O(D)$ memory buffer to accumulate department group totals.

---

### Exercise 2: Identify Election Winner with Tie Detection
- **Problem:** Write a query to return the winning candidate(s) for `election_id = 1`. If there is a tie for first place, return all tied candidates.
- **Solution (Using Window Functions - MySQL 8.0+ / MariaDB 10.4+):**
  ```sql
  WITH CandidateTallies AS (
      SELECT 
          c.id AS candidate_id,
          c.name AS candidate_name,
          COUNT(v.id) AS vote_count,
          DENSE_RANK() OVER (ORDER BY COUNT(v.id) DESC) AS ranking
      FROM candidates c
      LEFT JOIN votes v ON c.id = v.candidate_id AND v.election_id = 1
      WHERE c.election_id = 1 AND c.approval_status = 'approved'
      GROUP BY c.id, c.name
  )
  SELECT candidate_id, candidate_name, vote_count
  FROM CandidateTallies
  WHERE ranking = 1;
  ```
- **Complexity Analysis:**
  - **Time Complexity:** $O(C \log C + V_E)$, where $C$ is the candidate count in the election and $V_E$ is the number of cast ballots in this election. Index `idx_votes_election_candidate` ensures an index-only scan for vote counting.
  - **Space Complexity:** $O(C)$ to buffer candidate rank records.

---

### Exercise 3: Fraud Audit — Detect Ineligible Participation
- **Problem:** Write an audit query to find any participation record where the voter was NOT on the official eligibility whitelist at the time of voting.
- **Solution:**
  ```sql
  SELECT 
      ep.id AS participation_id,
      ep.election_id,
      ep.user_id,
      u.identifier,
      ep.voted_at
  FROM election_participation ep
  JOIN users u ON ep.user_id = u.id
  LEFT JOIN election_eligibility ee 
      ON ep.election_id = ee.election_id AND ep.user_id = ee.user_id
  WHERE ee.id IS NULL;
  ```
- **Expected Outcome:** 0 rows (returns empty set in a healthy VerityVote deployment).

---

## 12. Practical PHP Code-Reading & Debugging Exercises

Interviewers often provide a buggy code snippet and ask: *"What is wrong with this code, what vulnerability does it introduce, and how would you fix it?"*

### Debugging Scenario 1: The Race-Condition Double-Vote Bug
- **Buggy Snippet:**
  ```php
  // BUGGY IMPLEMENTATION
  public function castVote(int $electionId, int $candidateId, int $userId): bool {
      $hasVoted = $this->db->fetchValue(
          "SELECT id FROM election_participation WHERE election_id = $electionId AND user_id = $userId"
      );
      if ($hasVoted) {
          return false;
      }
      
      // Network delay / context switch occurs here!
      
      $this->db->execute("INSERT INTO election_participation (election_id, user_id) VALUES ($electionId, $userId)");
      $this->db->execute("INSERT INTO votes (election_id, candidate_id) VALUES ($electionId, $candidateId)");
      return true;
  }
  ```
- **Diagnosis:**
  1. **Time-of-Check to Time-of-Use (TOCTOU) Race Condition:** Two parallel requests can both execute the `SELECT` query, both find 0 rows, and both proceed to insert, allowing double-voting.
  2. **SQL Injection Vulnerability:** Raw variable interpolation (`$electionId`, `$userId`) rather than prepared statements.
  3. **Lack of Transaction Atomicity:** If the second `INSERT` fails, an orphaned participation record exists without a ballot.
- **The Correct Fix:** As implemented in [`VotingRepository.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Repositories/VotingRepository.php#L67): wrap in `$this->db->beginTransaction()`, lock the voter row using `SELECT ... FOR UPDATE`, execute parameterized queries, and commit or rollback atomically.

---

### Debugging Scenario 2: The Loose Type Invalidation Bug
- **Buggy Snippet:**
  ```php
  // BUGGY IMPLEMENTATION
  if ($user['role'] == 'admin') {
      $this->renderAdminDashboard();
  }
  ```
- **Diagnosis:**
  In PHP with loose comparison (`==`), if `$user['role']` evaluates to boolean `true` or integer `0` under certain mock or legacy PDO configurations, `0 == 'admin'` evaluated to `true` in PHP < 8.0.
- **The Correct Fix:** Use strict comparison `===` or `in_array($user['role'], ['admin'], true)` with strict mode enabled.
